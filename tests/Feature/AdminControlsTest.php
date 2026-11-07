<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminControlsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

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
    }

    // ============================================
    // W6.05 AC #1: Admin approval control
    // ============================================
    public function test_admin_can_approve_pending_rider_account(): void
    {
        $rider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Pending,
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/users/{$rider->id}/approve")
             ->assertOk()
             ->assertJsonPath('data.account_status', 'approved');

        $this->assertDatabaseHas('users', [
            'id'             => $rider->id,
            'account_status' => 'approved',
        ]);
    }

    public function test_non_admin_cannot_approve_accounts(): void
    {
        $pendingRider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Pending,
        ]);

        Sanctum::actingAs($this->customer); // not admin

        $this->postJson("/api/admin/users/{$pendingRider->id}/approve")
             ->assertStatus(403);

        // Unchanged
        $this->assertDatabaseHas('users', [
            'id'             => $pendingRider->id,
            'account_status' => 'pending',
        ]);
    }

    // ============================================
    // W6.05 AC #1: Admin fee control
    // ============================================
    public function test_admin_can_update_delivery_fee(): void
    {
        Sanctum::actingAs($this->admin);

        $this->patchJson('/api/admin/settings/fee', [
            'delivery_fee' => 50.00,
        ])->assertOk()
          ->assertJsonPath('data.delivery_fee', 50);

        $this->assertDatabaseHas('settings', [
            'key'   => 'delivery_fee',
            'value' => '50',
        ]);
    }

    public function test_non_admin_cannot_change_delivery_fee(): void
    {
        Sanctum::actingAs($this->customer);

        $this->patchJson('/api/admin/settings/fee', [
            'delivery_fee' => 50.00,
        ])->assertStatus(403);

        // Nothing persisted
        $this->assertDatabaseMissing('settings', [
            'key'   => 'delivery_fee',
            'value' => '50',
        ]);
    }

    // ============================================
    // W6.05 AC #2: Active orders keep quoted terms
    // ============================================
    public function test_active_order_keeps_frozen_delivery_fee_after_admin_change(): void
    {
        // 1. Create initial order via checkout (fee = 30)
        Sanctum::actingAs($this->customer);

        $restaurant = Restaurant::factory()->create(['is_open' => true, 'is_disabled' => false]);
        $item = MenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'price' => 100, 'is_available' => true]);

        $order = $this->postJson('/api/checkout', [
            'idempotency_key'  => 'frozen-' . uniqid(),
            'restaurant_id'    => $restaurant->id,
            'items'            => [['menu_item_id' => $item->id, 'quantity' => 1]],
            'delivery_address' => '123 Main St',
            'payment_method'   => 'cod',
        ])->assertStatus(201);

        $orderId = $order->json('data.id');

        // Confirm order has fee = 30 + frozen fee = 30
        $this->assertDatabaseHas('orders', [
            'id'                  => $orderId,
            'delivery_fee'        => 30.00,
            'frozen_delivery_fee' => 30.00,
        ]);

        // 2. Admin changes delivery fee to 50
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/settings/fee', ['delivery_fee' => 50.00])->assertOk();

        // 3. Existing order still has 30 (frozen)
        $order = Order::find($orderId);
        $this->assertEquals('30.00', $order->delivery_fee);
        $this->assertEquals('30.00', $order->frozen_delivery_fee);
    }

    // ============================================
    // Extra: settings read endpoint
    // ============================================
    public function test_admin_can_view_current_settings(): void
    {
        Setting::set('delivery_fee', '40');

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/settings')
             ->assertOk()
             ->assertJsonPath('data.delivery_fee', 40);
    }
}