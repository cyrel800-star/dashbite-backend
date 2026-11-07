<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\DispatchAttempt;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $rider;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['is_open' => true, 'is_disabled' => false]);
        
        $customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);

        $this->rider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Approved,
        ]);

        RiderProfile::create([
            'user_id'      => $this->rider->id,
            'is_online'    => true,
            'is_available' => false, // currently on delivery
        ]);

        // Order initially in 'ready' state
        $this->order = Order::create([
            'idempotency_key'  => 'delivery-' . uniqid(),
            'customer_id'      => $customer->id,
            'restaurant_id'    => $this->restaurant->id,
            'status'           => OrderStatus::Ready,
            'payment_method'   => 'cod',
            'subtotal'         => 100.00,
            'delivery_fee'     => 30.00,
            'total'            => 130.00,
            'delivery_address' => '123 Main St',
            'placed_at'        => now(),
        ]);

        // Simulate the rider winning the dispatch
        DispatchAttempt::create([
            'order_id'        => $this->order->id,
            'ring_km'         => 1,
            'status'          => 'assigned',
            'winner_rider_id' => $this->rider->id,
            'started_at'      => now(),
            'ended_at'        => now(),
        ]);

        Sanctum::actingAs($this->rider);
    }

    // ============================================
    // W6.02 AC #1: Progression through pickup → delivered
    // ============================================
    public function test_rider_can_mark_order_picked_up(): void
    {
        $this->patchJson("/api/rider/orders/{$this->order->id}/pickup")
             ->assertOk()
             ->assertJsonPath('data.status', 'picked_up');

        $this->assertDatabaseHas('orders', [
            'id'     => $this->order->id,
            'status' => 'picked_up',
        ]);
    }

    public function test_rider_can_mark_order_delivered_with_cod(): void
    {
        // 1. Pickup
        $this->patchJson("/api/rider/orders/{$this->order->id}/pickup")->assertOk();

        // 2. Deliver + record COD
        $this->patchJson("/api/rider/orders/{$this->order->id}/delivered", [
            'cash_collected' => 130.00,
        ])->assertOk()
          ->assertJsonPath('data.status', 'delivered');

        $this->assertDatabaseHas('orders', [
            'id'                => $this->order->id,
            'status'            => 'delivered',
            'cash_collected'    => 130.00,
        ]);

        // Rider is available again
        $this->assertTrue((bool) RiderProfile::where('user_id', $this->rider->id)->first()->is_available);
    }

    // ============================================
    // W6.02 AC #2: Invalid transitions rejected
    // ============================================
    public function test_cannot_mark_delivered_before_pickup(): void
    {
        $this->patchJson("/api/rider/orders/{$this->order->id}/delivered", [
            'cash_collected' => 130.00,
        ])->assertStatus(422)
          ->assertJsonPath('error', 'invalid_transition');

        // Status unchanged
        $this->assertEquals('ready', $this->order->fresh()->status->value);
    }

    public function test_cod_delivery_requires_cash_amount(): void
    {
        $this->patchJson("/api/rider/orders/{$this->order->id}/pickup")->assertOk();

        $this->patchJson("/api/rider/orders/{$this->order->id}/delivered", [])
             ->assertStatus(422)
             ->assertJsonPath('error', 'invalid_transition'); // Cash validation triggers via lifecycle service
    }

    // ============================================
    // Authorization guards
    // ============================================
    public function test_rider_cannot_update_order_not_assigned_to_them(): void
    {
        $otherRider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Approved,
        ]);
        Sanctum::actingAs($otherRider);

        $this->patchJson("/api/rider/orders/{$this->order->id}/pickup")
             ->assertStatus(403)
             ->assertJson(['error' => 'order_not_assigned_to_you']);
    }

    public function test_customer_cannot_access_rider_delivery_endpoints(): void
    {
        $customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);
        Sanctum::actingAs($customer);

        $this->patchJson("/api/rider/orders/{$this->order->id}/pickup")
             ->assertStatus(403);
    }
}