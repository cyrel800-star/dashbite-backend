<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\DispatchAttempt;
use App\Models\DispatchOffer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\DispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Order $order;
    private User $rider1;
    private User $rider2;

    protected function setUp(): void
    {
        parent::setUp();

        // Restaurant at center (14.5995, 120.9842)
        $this->restaurant = Restaurant::factory()->create([
            'is_open'          => true,
            'is_disabled'      => false,
            'service_boundary' => ['lat_center' => 14.5995, 'lng_center' => 120.9842],
        ]);

        $customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);

        $this->order = Order::create([
            'idempotency_key'  => 'dispatch-' . uniqid(),
            'customer_id'      => $customer->id,
            'restaurant_id'    => $this->restaurant->id,
            'status'           => OrderStatus::Pending,
            'payment_method'   => 'cod',
            'subtotal'         => 100,
            'delivery_fee'     => 30,
            'total'            => 130,
            'delivery_address' => '123 Main St',
            'placed_at'        => now(),
        ]);

        // Two riders at ~0.5km from restaurant (inside 1km ring)
        $this->rider1 = User::factory()->create(['role' => UserRole::Rider, 'account_status' => AccountStatus::Approved]);
        $this->rider2 = User::factory()->create(['role' => UserRole::Rider, 'account_status' => AccountStatus::Approved]);

        RiderProfile::create([
            'user_id'          => $this->rider1->id,
            'is_online'        => true,
            'is_available'     => true,
            'current_lat'      => 14.6040,
            'current_lng'      => 120.9842,
            'last_location_at' => now(),
        ]);

        RiderProfile::create([
            'user_id'          => $this->rider2->id,
            'is_online'        => true,
            'is_available'     => true,
            'current_lat'      => 14.5950,
            'current_lng'      => 120.9842,
            'last_location_at' => now(),
        ]);
    }

    // ==================================================
    // W6.01 AC #1: Simultaneous 1km blast to eligible riders
    // ==================================================
    public function test_blast_creates_offers_for_all_eligible_riders_in_1km_ring(): void
    {
        $attempt = (new DispatchService())->start($this->order);

        $this->assertEquals(1, $attempt->ring_km);
        $this->assertEquals('broadcasting', $attempt->status);
        $this->assertEquals(2, $attempt->offers->count());

        $this->assertDatabaseHas('dispatch_offers', [
            'dispatch_attempt_id' => $attempt->id,
            'rider_id'            => $this->rider1->id,
            'status'              => 'pending',
        ]);
        $this->assertDatabaseHas('dispatch_offers', [
            'dispatch_attempt_id' => $attempt->id,
            'rider_id'            => $this->rider2->id,
            'status'              => 'pending',
        ]);
    }

    public function test_rider_outside_1km_ring_not_offered(): void
    {
        // Move rider2 ~5km away
        RiderProfile::where('user_id', $this->rider2->id)->update([
            'current_lat' => 14.6500,
            'current_lng' => 120.9842,
        ]);

        $attempt = (new DispatchService())->start($this->order);

        $this->assertEquals(1, $attempt->offers->count());
        $this->assertEquals($this->rider1->id, $attempt->offers->first()->rider_id);
    }

    // ==================================================
    // W6.01 AC #2: Exactly one winner when two accept at same time
    // ==================================================
    public function test_only_one_rider_wins_on_simultaneous_accepts(): void
    {
        $service = new DispatchService();
        $attempt = $service->start($this->order);
        $offers = $attempt->offers;

        $offer1 = $offers->firstWhere('rider_id', $this->rider1->id);
        $offer2 = $offers->firstWhere('rider_id', $this->rider2->id);

        // Simulate race: both accept; first one wins
        $won1 = $service->accept($offer1, $this->rider1);
        $won2 = $service->accept($offer2, $this->rider2);

        $this->assertTrue($won1, 'Rider 1 should win');
        $this->assertFalse($won2, 'Rider 2 should lose');

        // Attempt has one winner
        $attempt->refresh();
        $this->assertEquals('assigned', $attempt->status);
        $this->assertEquals($this->rider1->id, $attempt->winner_rider_id);

        // Order status updated
        $this->assertEquals(OrderStatus::Accepted, $this->order->fresh()->status);

        // Rider 1 marked busy, rider 2 stays available
        $this->assertFalse((bool) RiderProfile::where('user_id', $this->rider1->id)->first()->is_available);
        $this->assertTrue((bool) RiderProfile::where('user_id', $this->rider2->id)->first()->is_available);

        // Other offer closed
        $this->assertEquals('closed', $offer2->fresh()->status);
    }

    public function test_cannot_accept_offer_not_owned_by_rider(): void
    {
        $service = new DispatchService();
        $attempt = $service->start($this->order);
        $offer1 = $attempt->offers->firstWhere('rider_id', $this->rider1->id);

        // Rider 2 tries to accept rider 1's offer
        $this->assertFalse($service->accept($offer1, $this->rider2));
    }

    // ==================================================
    // Rider API accept endpoint
    // ==================================================
    public function test_rider_can_accept_offer_via_api(): void
    {
        $service = new DispatchService();
        $attempt = $service->start($this->order);
        $offer = $attempt->offers->firstWhere('rider_id', $this->rider1->id);

        Sanctum::actingAs($this->rider1);

        $this->postJson("/api/rider/offers/{$offer->id}/accept")
             ->assertOk()
             ->assertJsonPath('data.status', 'assigned')
             ->assertJsonPath('data.order_id', $this->order->id);
    }

    public function test_rider_cannot_accept_offer_via_api_if_lost_race(): void
    {
        $service = new DispatchService();
        $attempt = $service->start($this->order);

        $offer1 = $attempt->offers->firstWhere('rider_id', $this->rider1->id);
        $offer2 = $attempt->offers->firstWhere('rider_id', $this->rider2->id);

        // Rider 1 wins first
        $service->accept($offer1, $this->rider1);

        // Rider 2 tries via API
        Sanctum::actingAs($this->rider2);

        $this->postJson("/api/rider/offers/{$offer2->id}/accept")
             ->assertStatus(409)
             ->assertJson(['error' => 'offer_no_longer_available']);
    }

    // ==================================================
    // Exclusions
    // ==================================================
    public function test_busy_rider_not_offered(): void
    {
        RiderProfile::where('user_id', $this->rider2->id)->update(['is_available' => false]);

        $attempt = (new DispatchService())->start($this->order);

        $this->assertEquals(1, $attempt->offers->count());
        $this->assertEquals($this->rider1->id, $attempt->offers->first()->rider_id);
    }

    public function test_stale_location_rider_not_offered(): void
    {
        RiderProfile::where('user_id', $this->rider2->id)->update([
            'last_location_at' => now()->subMinutes(10),
        ]);

        $attempt = (new DispatchService())->start($this->order);

        $this->assertEquals(1, $attempt->offers->count());
        $this->assertEquals($this->rider1->id, $attempt->offers->first()->rider_id);
    }
}