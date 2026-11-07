<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\DispatchAttempt;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RatingsMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $rider;
    private User $admin;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'           => UserRole::Admin,
            'account_status' => AccountStatus::Approved,
        ]);

        $this->customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);

        $this->rider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Approved,
        ]);

        $restaurant = Restaurant::factory()->create();

        $this->order = Order::create([
            'idempotency_key'  => 'rate-' . uniqid(),
            'customer_id'      => $this->customer->id,
            'restaurant_id'    => $restaurant->id,
            'status'           => OrderStatus::Delivered,
            'payment_method'   => 'cod',
            'subtotal'         => 100.00,
            'delivery_fee'     => 30.00,
            'total'            => 130.00,
            'delivery_address' => '123 Main St',
            'placed_at'        => now(),
            'delivered_at'     => now(),
        ]);

        DispatchAttempt::create([
            'order_id'        => $this->order->id,
            'ring_km'         => 1,
            'status'          => 'assigned',
            'winner_rider_id' => $this->rider->id,
            'started_at'      => now(),
            'ended_at'        => now(),
        ]);
    }

    // ============================================
    // W7.01 AC #1: Separate ratings for restaurant and rider
    // ============================================
    public function test_customer_can_rate_restaurant_and_rider_separately(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson("/api/checkout/orders/{$this->order->id}/rate", [
            'restaurant_rating'  => 5,
            'rider_rating'       => 4,
            'restaurant_comment' => 'Great food!',
            'rider_comment'      => 'On time.',
        ])->assertStatus(201)
          ->assertJsonPath('data.restaurant_rating', 5)
          ->assertJsonPath('data.rider_rating', 4);

        $this->assertDatabaseHas('ratings', [
            'order_id'          => $this->order->id,
            'restaurant_rating' => 5,
            'rider_rating'      => 4,
            'rider_id'          => $this->rider->id,
        ]);
    }

    // ============================================
    // W7.01 AC #3: Only the customer of the order can rate
    // ============================================
    public function test_other_customer_cannot_rate_order(): void
    {
        $other = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);

        Sanctum::actingAs($other);

        $this->postJson("/api/checkout/orders/{$this->order->id}/rate", [
            'restaurant_rating' => 5,
        ])->assertStatus(403)
          ->assertJson(['error' => 'not_your_order']);
    }

    public function test_cannot_rate_undelivered_order(): void
    {
        $this->order->update(['status' => OrderStatus::PickedUp]);

        Sanctum::actingAs($this->customer);

        $this->postJson("/api/checkout/orders/{$this->order->id}/rate", [
            'restaurant_rating' => 5,
        ])->assertStatus(422)
          ->assertJson(['error' => 'order_not_delivered']);
    }

    public function test_cannot_rate_same_order_twice(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson("/api/checkout/orders/{$this->order->id}/rate", [
            'restaurant_rating' => 5,
        ])->assertStatus(201);

        $this->postJson("/api/checkout/orders/{$this->order->id}/rate", [
            'restaurant_rating' => 4,
        ])->assertStatus(409)
          ->assertJson(['error' => 'already_rated']);
    }

    // ============================================
    // W7.01 AC #2: Ratings/attendance do not affect dispatch eligibility
    // ============================================
    public function test_ratings_do_not_affect_dispatch_eligibility(): void
    {
        // Give this rider a terrible rating and another rider a perfect one.
        $otherRider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Approved,
        ]);

        // Set up profiles
        \App\Models\RiderProfile::create([
            'user_id'      => $this->rider->id,
            'is_online'    => true,
            'is_available' => true,
            'current_lat'  => 14.5,
            'current_lng'  => 121.0,
            'last_location_at' => now(),
        ]);
        \App\Models\RiderProfile::create([
            'user_id'      => $otherRider->id,
            'is_online'    => true,
            'is_available' => true,
            'current_lat'  => 14.5,
            'current_lng'  => 121.0,
            'last_location_at' => now(),
        ]);

        // Create low rating for rider 1
        Rating::create([
            'order_id'          => $this->order->id,
            'customer_id'       => $this->customer->id,
            'rider_id'          => $this->rider->id,
            'restaurant_rating' => 1,
            'rider_rating'      => 1,
        ]);

        // Both riders must be in the eligibility pool (rating doesn't matter)
        $service = new \App\Services\DispatchService();
        $eligible = $service->eligibleRidersWithin(14.5, 121.0, 5);

        $ids = $eligible->pluck('user_id')->all();
        $this->assertContains($this->rider->id, $ids);
        $this->assertContains($otherRider->id, $ids);
    }

    // ============================================
    // W7.01 AC #4: Admin monitoring endpoints
    // ============================================
    public function test_admin_can_view_ratings(): void
    {
        Rating::create([
            'order_id'          => $this->order->id,
            'customer_id'       => $this->customer->id,
            'rider_id'          => $this->rider->id,
            'restaurant_rating' => 5,
            'rider_rating'      => 4,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/ratings')
             ->assertOk()
             ->assertJsonPath('data.0.restaurant_rating', 5)
             ->assertJsonPath('data.0.rider_rating', 4);
    }

    public function test_admin_can_view_attendance(): void
    {
        \App\Models\RiderAttendance::create([
            'rider_id'    => $this->rider->id,
            'date'        => now()->toDateString(),
            'deliveries_count' => 5,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/attendance')
             ->assertOk()
             ->assertJsonPath('data.0.deliveries_count', 5);
    }

    public function test_customer_can_file_complaint(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/checkout/complaints', [
            'order_id' => $this->order->id,
            'subject'  => 'Cold food',
            'body'     => 'Food arrived cold.',
        ])->assertStatus(201)
          ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('complaints', [
            'customer_id' => $this->customer->id,
            'order_id'    => $this->order->id,
            'status'      => 'open',
        ]);
    }

    public function test_admin_can_resolve_complaint(): void
    {
        $complaint = Complaint::create([
            'customer_id' => $this->customer->id,
            'order_id'    => $this->order->id,
            'subject'     => 'Cold food',
            'body'        => 'Food arrived cold.',
            'status'      => 'open',
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/complaints/{$complaint->id}/resolve", [
            'resolution_notes' => 'Refunded 50%.',
        ])->assertOk()
          ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('complaints', [
            'id'     => $complaint->id,
            'status' => 'resolved',
        ]);
    }

    public function test_non_admin_cannot_access_admin_monitoring(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/admin/ratings')->assertStatus(403);
        $this->getJson('/api/admin/attendance')->assertStatus(403);
        $this->getJson('/api/admin/complaints')->assertStatus(403);
    }
}