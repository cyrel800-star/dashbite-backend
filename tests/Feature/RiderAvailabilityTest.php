<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\RiderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Approved,
        ]);

        Sanctum::actingAs($this->rider);
    }

    // ============================================
    // Basic endpoints
    // ============================================
    public function test_rider_can_toggle_online_and_availability(): void
    {
        $this->postJson('/api/rider/availability', [
            'is_online'    => true,
            'is_available' => true,
        ])->assertOk()
          ->assertJsonPath('data.is_online', true)
          ->assertJsonPath('data.is_available', true);

        $this->assertDatabaseHas('rider_profiles', [
            'user_id'      => $this->rider->id,
            'is_online'    => true,
            'is_available' => true,
        ]);
    }

    public function test_rider_can_update_location(): void
    {
        $this->patchJson('/api/rider/location', [
            'lat' => 14.5995,
            'lng' => 120.9842,
        ])->assertOk()
          ->assertJsonPath('data.current_lat', '14.5995000')
          ->assertJsonPath('data.current_lng', '120.9842000');

        $this->assertDatabaseHas('rider_profiles', [
            'user_id'     => $this->rider->id,
            'current_lat' => 14.5995,
            'current_lng' => 120.9842,
        ]);
    }

    // ============================================
    // W5.01 AC #1: Exclusion rules
    // ============================================
    public function test_offline_rider_excluded_from_eligibility(): void
    {
        RiderProfile::create([
            'user_id'          => $this->rider->id,
            'is_online'        => false,
            'is_available'     => true,
            'current_lat'      => 14.5,
            'current_lng'      => 121.0,
            'last_location_at' => now(),
        ]);

        $eligible = (new RiderService())->eligibleRiderIds();
        $this->assertEmpty($eligible);
    }

    public function test_unapproved_rider_excluded_from_eligibility(): void
    {
        $this->rider->update(['account_status' => AccountStatus::Pending]);

        RiderProfile::create([
            'user_id'          => $this->rider->id,
            'is_online'        => true,
            'is_available'     => true,
            'current_lat'      => 14.5,
            'current_lng'      => 121.0,
            'last_location_at' => now(),
        ]);

        $eligible = (new RiderService())->eligibleRiderIds();
        $this->assertEmpty($eligible);
    }

    public function test_busy_rider_excluded_from_eligibility(): void
    {
        RiderProfile::create([
            'user_id'          => $this->rider->id,
            'is_online'        => true,
            'is_available'     => false, // already delivering
            'current_lat'      => 14.5,
            'current_lng'      => 121.0,
            'last_location_at' => now(),
        ]);

        $eligible = (new RiderService())->eligibleRiderIds();
        $this->assertEmpty($eligible);
    }

    public function test_stale_location_rider_excluded_from_eligibility(): void
    {
        RiderProfile::create([
            'user_id'          => $this->rider->id,
            'is_online'        => true,
            'is_available'     => true,
            'current_lat'      => 14.5,
            'current_lng'      => 121.0,
            'last_location_at' => now()->subMinutes(10), // stale > 5 min
        ]);

        $eligible = (new RiderService())->eligibleRiderIds();
        $this->assertEmpty($eligible);
    }

    public function test_eligible_rider_included_when_all_conditions_met(): void
    {
        RiderProfile::create([
            'user_id'          => $this->rider->id,
            'is_online'        => true,
            'is_available'     => true,
            'current_lat'      => 14.5,
            'current_lng'      => 121.0,
            'last_location_at' => now(),
        ]);

        $eligible = (new RiderService())->eligibleRiderIds();
        $this->assertContains($this->rider->id, $eligible);
    }

    public function test_missing_location_rider_excluded(): void
    {
        RiderProfile::create([
            'user_id'          => $this->rider->id,
            'is_online'        => true,
            'is_available'     => true,
            'current_lat'      => null,
            'current_lng'      => null,
            'last_location_at' => null,
        ]);

        $eligible = (new RiderService())->eligibleRiderIds();
        $this->assertEmpty($eligible);
    }

    // ============================================
    // W5.01 AC #2: Latest state is checked before assignment
    // ============================================
    public function test_going_offline_after_evaluation_invalidates_eligibility(): void
    {
        RiderProfile::create([
            'user_id'          => $this->rider->id,
            'is_online'        => true,
            'is_available'     => true,
            'current_lat'      => 14.5,
            'current_lng'      => 121.0,
            'last_location_at' => now(),
        ]);

        $service = new RiderService();
        $this->assertContains($this->rider->id, $service->eligibleRiderIds());

        // Rider goes offline after eligibility was evaluated
        $this->postJson('/api/rider/availability', [
            'is_online'    => false,
            'is_available' => true,
        ])->assertOk();

        $this->assertNotContains($this->rider->id, $service->eligibleRiderIds());
    }

    // ============================================
    // Status endpoint
    // ============================================
    public function test_rider_can_view_own_status(): void
    {
        RiderProfile::create([
            'user_id'          => $this->rider->id,
            'is_online'        => true,
            'is_available'     => true,
            'current_lat'      => 14.5,
            'current_lng'      => 121.0,
            'last_location_at' => now(),
        ]);

        $this->getJson('/api/rider/status')
             ->assertOk()
             ->assertJsonPath('data.is_online', true)
             ->assertJsonPath('data.has_fresh_location', true)
             ->assertJsonPath('data.is_eligible', true);
    }
}