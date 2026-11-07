<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExternalOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $restaurantUser;
    private Restaurant $restaurant;
    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'is_open'     => true,
            'is_disabled' => false,
        ]);

        $this->restaurantUser = User::factory()->create([
            'role'           => UserRole::Restaurant,
            'account_status' => AccountStatus::Approved,
            'restaurant_id'  => $this->restaurant->id,
        ]);

        $this->item = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price'         => 100.00,
            'is_available'  => true,
        ]);

        Sanctum::actingAs($this->restaurantUser);
    }

    // ============================================
    // Happy path — phone order
    // ============================================
    public function test_restaurant_can_create_external_order_via_phone(): void
    {
        $response = $this->postJson('/api/restaurant/orders/external', [
            'source'                  => 'phone',
            'external_customer_name'  => 'Juan dela Cruz',
            'external_customer_phone' => '09171234567',
            'delivery_address'        => '123 Main St',
            'payment_method'          => 'cod',
            'items'                   => [
                ['menu_item_id' => $this->item->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.source', 'phone')
                 ->assertJsonPath('data.external_customer_name', 'Juan dela Cruz')
                 ->assertJsonPath('data.status', 'pending')
                 ->assertJsonPath('data.subtotal', '200.00')
                 ->assertJsonPath('data.delivery_fee', '30.00')
                 ->assertJsonPath('data.total', '230.00');

        // Order saved with restaurant_id linked
        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $this->restaurant->id,
            'source'        => 'phone',
            'status'        => 'pending',
        ]);
    }

    public function test_restaurant_can_create_external_walk_in_order(): void
    {
        $this->postJson('/api/restaurant/orders/external', [
            'source'                  => 'walk_in',
            'external_customer_name'  => 'Maria Santos',
            'external_customer_phone' => '09181234567',
            'delivery_address'        => '456 Other St',
            'payment_method'          => 'cod',
            'items'                   => [
                ['menu_item_id' => $this->item->id, 'quantity' => 1],
            ],
        ])->assertStatus(201)
          ->assertJsonPath('data.source', 'walk_in');
    }

    // ============================================
    // W4.03 AC #1: Missing customer details → rejected (no partial order)
    // ============================================
    public function test_missing_external_customer_name_rejected(): void
    {
        $this->postJson('/api/restaurant/orders/external', [
            'source'                  => 'phone',
            'external_customer_phone' => '09171234567',
            'delivery_address'        => '123 Main St',
            'payment_method'          => 'cod',
            'items'                   => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['external_customer_name']);

        $this->assertEquals(0, Order::count());
    }

    public function test_missing_external_customer_phone_rejected(): void
    {
        $this->postJson('/api/restaurant/orders/external', [
            'source'                  => 'phone',
            'external_customer_name'  => 'Juan dela Cruz',
            'delivery_address'        => '123 Main St',
            'payment_method'          => 'cod',
            'items'                   => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['external_customer_phone']);

        $this->assertEquals(0, Order::count());
    }

    public function test_missing_delivery_address_rejected(): void
    {
        $this->postJson('/api/restaurant/orders/external', [
            'source'                  => 'phone',
            'external_customer_name'  => 'Juan dela Cruz',
            'external_customer_phone' => '09171234567',
            'payment_method'          => 'cod',
            'items'                   => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['delivery_address']);

        $this->assertEquals(0, Order::count());
    }

    // ============================================
    // W4.03 AC #2: Follows same lifecycle (pending → accept → preparing → ready)
    // ============================================
    public function test_external_order_follows_same_lifecycle_as_web_order(): void
    {
        // 1. Create external order
        $create = $this->postJson('/api/restaurant/orders/external', [
            'source'                  => 'phone',
            'external_customer_name'  => 'Juan dela Cruz',
            'external_customer_phone' => '09171234567',
            'delivery_address'        => '123 Main St',
            'payment_method'          => 'cod',
            'items'                   => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $orderId = $create->json('data.id');

        // 2. Accept — should work same as web order
        $this->postJson("/api/restaurant/orders/{$orderId}/accept", [
            'prep_minutes' => 20,
        ])->assertOk()
          ->assertJsonPath('data.status', 'accepted');

        // 3. Preparing
        $this->patchJson("/api/restaurant/orders/{$orderId}/preparing")
             ->assertOk()
             ->assertJsonPath('data.status', 'preparing');

        // 4. Ready
        $this->patchJson("/api/restaurant/orders/{$orderId}/ready")
             ->assertOk()
             ->assertJsonPath('data.status', 'ready');
    }

    // ============================================
    // Guards
    // ============================================
    public function test_cannot_use_menu_item_from_other_restaurant(): void
    {
        $other = Restaurant::factory()->create();
        $otherItem = MenuItem::factory()->create(['restaurant_id' => $other->id]);

        $this->postJson('/api/restaurant/orders/external', [
            'source'                  => 'phone',
            'external_customer_name'  => 'Juan dela Cruz',
            'external_customer_phone' => '09171234567',
            'delivery_address'        => '123 Main St',
            'payment_method'          => 'cod',
            'items'                   => [['menu_item_id' => $otherItem->id, 'quantity' => 1]],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['items']);
    }

    public function test_cannot_use_out_of_stock_item(): void
    {
        $this->item->update(['is_available' => false]);

        $this->postJson('/api/restaurant/orders/external', [
            'source'                  => 'phone',
            'external_customer_name'  => 'Juan dela Cruz',
            'external_customer_phone' => '09171234567',
            'delivery_address'        => '123 Main St',
            'payment_method'          => 'cod',
            'items'                   => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['items']);
    }

    public function test_customer_role_cannot_create_external_order(): void
    {
        $customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/restaurant/orders/external', [])->assertStatus(403);
    }
}