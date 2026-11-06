<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $restaurantUser;
    private Restaurant $restaurant;
    private User $customer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['is_open' => true, 'is_disabled' => false]);

        $this->restaurantUser = User::factory()->create([
            'role'           => UserRole::Restaurant,
            'account_status' => AccountStatus::Approved,
            'restaurant_id'  => $this->restaurant->id,
        ]);

        $this->customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);

        $item = MenuItem::factory()->create(['restaurant_id' => $this->restaurant->id, 'price' => 100]);

        // Create initial pending order
        $this->order = Order::create([
            'idempotency_key'  => 'seed-' . uniqid(),
            'customer_id'      => $this->customer->id,
            'restaurant_id'    => $this->restaurant->id,
            'status'           => OrderStatus::Pending,
            'payment_method'   => 'cod',
            'subtotal'         => 100.00,
            'delivery_fee'     => 30.00,
            'total'            => 130.00,
            'delivery_address' => '123 Main St',
            'placed_at'        => now(),
        ]);

        Sanctum::actingAs($this->restaurantUser);
    }

    // ==================================================
    // W4.01 AC #1 — Rejected order MUST NOT trigger rider search
    // ==================================================
    public function test_restaurant_can_reject_order_with_reason(): void
    {
        $this->postJson("/api/restaurant/orders/{$this->order->id}/reject", [
            'reason' => 'Ingredients out of stock',
        ])->assertOk()
          ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('orders', [
            'id'     => $this->order->id,
            'status' => 'rejected',
        ]);

        // Audit trail recorded
        $this->assertDatabaseHas('order_status_history', [
            'order_id'    => $this->order->id,
            'from_status' => 'pending',
            'to_status'   => 'rejected',
            'reason'      => 'Ingredients out of stock',
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        $this->postJson("/api/restaurant/orders/{$this->order->id}/reject", [
            'reason' => '',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['reason']);
    }

    // ==================================================
    // Happy path: accept → preparing → ready
    // ==================================================
    public function test_restaurant_accept_and_progress_order_to_ready(): void
    {
        // Accept (with prep time)
        $this->postJson("/api/restaurant/orders/{$this->order->id}/accept", [
            'prep_minutes' => 25,
        ])->assertOk()
          ->assertJsonPath('data.status', 'accepted');

        // Preparing
        $this->patchJson("/api/restaurant/orders/{$this->order->id}/preparing")
             ->assertOk()
             ->assertJsonPath('data.status', 'preparing');

        // Ready
        $this->patchJson("/api/restaurant/orders/{$this->order->id}/ready")
             ->assertOk()
             ->assertJsonPath('data.status', 'ready');

        // Verify history chain (transitions only — initial pending state
        // is created via Order::create in setUp, not via the service)
        $this->assertEquals(
            ['accepted', 'preparing', 'ready'],
            OrderStatusHistory::where('order_id', $this->order->id)
                ->orderBy('created_at')->orderBy('id')->pluck('to_status')->map->value->all()
        );
    }

    // ==================================================
    // W4.01 AC #2 — Out-of-sequence transitions are rejected
    // ==================================================
    public function test_cannot_mark_ready_before_accepted(): void
    {
        $this->patchJson("/api/restaurant/orders/{$this->order->id}/ready")
             ->assertStatus(422)
             ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('orders', [
            'id'     => $this->order->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_accept_already_accepted_order(): void
    {
        $this->postJson("/api/restaurant/orders/{$this->order->id}/accept")->assertOk();
        $this->postJson("/api/restaurant/orders/{$this->order->id}/accept")
             ->assertStatus(422)
             ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_accept_rejected_order(): void
    {
        $this->postJson("/api/restaurant/orders/{$this->order->id}/reject", ['reason' => 'No stock'])->assertOk();
        $this->postJson("/api/restaurant/orders/{$this->order->id}/accept")
             ->assertStatus(422)
             ->assertJsonValidationErrors(['status']);
    }

    // ==================================================
    // Authorization guards
    // ==================================================
    public function test_restaurant_cannot_manage_other_restaurant_orders(): void
    {
        $other = Restaurant::factory()->create();
        $otherOrder = Order::create([
            'idempotency_key'  => 'other-' . uniqid(),
            'customer_id'      => $this->customer->id,
            'restaurant_id'    => $other->id,
            'status'           => OrderStatus::Pending,
            'payment_method'   => 'cod',
            'subtotal'         => 50.00,
            'delivery_fee'     => 30.00,
            'total'            => 80.00,
            'delivery_address' => '456 Other St',
            'placed_at'        => now(),
        ]);

        $this->postJson("/api/restaurant/orders/{$otherOrder->id}/accept")
             ->assertStatus(404);
    }

    public function test_customer_cannot_access_restaurant_order_endpoints(): void
    {
        Sanctum::actingAs($this->customer);
        $this->getJson('/api/restaurant/orders')->assertStatus(403);
    }
}