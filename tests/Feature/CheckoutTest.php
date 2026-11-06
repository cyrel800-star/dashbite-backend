<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Restaurant $restaurant;
    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup standard fixtures
        $this->customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);

        $this->restaurant = Restaurant::factory()->create([
            'is_open'     => true,
            'is_disabled' => false,
        ]);

        $this->item = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price'         => 100.00,
            'is_available'  => true,
        ]);

        Sanctum::actingAs($this->customer);
    }

    // ============================================
    // W3.04 AC #1: Food subtotal, delivery fee, total shown before placement
    // (Server computes & returns totals based on current price snapshot)
    // ============================================
    public function test_checkout_calculates_subtotal_and_delivery_fee(): void
    {
        $response = $this->postJson('/api/checkout', [
            'idempotency_key'  => 'key-ac1-' . uniqid(),
            'restaurant_id'    => $this->restaurant->id,
            'items'            => [
                ['menu_item_id' => $this->item->id, 'quantity' => 2],
            ],
            'delivery_address' => '123 Main St',
            'payment_method'   => 'cod',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.subtotal', '200.00')
                 ->assertJsonPath('data.delivery_fee', '30.00')
                 ->assertJsonPath('data.total', '230.00')
                 ->assertJsonPath('data.status', 'pending')
                 ->assertJsonPath('data.items.0.unit_price', '100.00')
                 ->assertJsonPath('data.items.0.quantity', 2)
                 ->assertJsonPath('data.items.0.line_total', '200.00');
    }

    // ============================================
    // W3.04 AC #1 part 2: Out-of-stock item cannot be silently accepted
    // ============================================
    public function test_checkout_rejects_out_of_stock_item(): void
    {
        $soldOut = MenuItem::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_available'  => false,
        ]);

        $this->postJson('/api/checkout', [
            'idempotency_key'  => 'key-oos-' . uniqid(),
            'restaurant_id'    => $this->restaurant->id,
            'items'            => [['menu_item_id' => $soldOut->id, 'quantity' => 1]],
            'delivery_address' => '123 Main St',
            'payment_method'   => 'cod',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['items']);
    }

    // ============================================
    // W3.04 AC #2: Outside service boundary → order rejected
    // ============================================
    public function test_checkout_rejects_outside_service_boundary(): void
    {
        $this->restaurant->update([
            'service_boundary' => [
                'lat_min' => 14.0,
                'lat_max' => 14.5,
                'lng_min' => 120.9,
                'lng_max' => 121.1,
            ],
        ]);

        $this->postJson('/api/checkout', [
            'idempotency_key'  => 'key-bound-' . uniqid(),
            'restaurant_id'    => $this->restaurant->id,
            'items'            => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
            'delivery_address' => '123 Main St',
            'delivery_lat'     => 10.0,    // way outside
            'delivery_lng'     => 100.0,
            'payment_method'   => 'cod',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['delivery_address']);
    }

    // ============================================
    // W3.04 AC #3: Same idempotency_key → only one order created
    // ============================================
    public function test_checkout_duplicate_submission_creates_only_one_order(): void
    {
        $key = 'key-idem-' . uniqid();
        $payload = [
            'idempotency_key'  => $key,
            'restaurant_id'    => $this->restaurant->id,
            'items'            => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
            'delivery_address' => '123 Main St',
            'payment_method'   => 'cod',
        ];

        $first  = $this->postJson('/api/checkout', $payload)->assertStatus(201);
        $second = $this->postJson('/api/checkout', $payload)->assertStatus(201);

        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
            'Duplicate submission must return the SAME order.'
        );

        $this->assertSame(1, Order::where('idempotency_key', $key)->count());
    }

    // ============================================
    // Guards
    // ============================================
    public function test_disabled_restaurant_cannot_receive_order(): void
    {
        $this->restaurant->update(['is_disabled' => true]);

        $this->postJson('/api/checkout', [
            'idempotency_key'  => 'key-dis-' . uniqid(),
            'restaurant_id'    => $this->restaurant->id,
            'items'            => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
            'delivery_address' => '123 Main St',
            'payment_method'   => 'cod',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['restaurant_id']);
    }

    public function test_unapproved_customer_cannot_checkout(): void
    {
        $this->customer->update(['account_status' => AccountStatus::Pending]);

        $this->postJson('/api/checkout', [
            'idempotency_key'  => 'key-unapp-' . uniqid(),
            'restaurant_id'    => $this->restaurant->id,
            'items'            => [['menu_item_id' => $this->item->id, 'quantity' => 1]],
            'delivery_address' => '123 Main St',
            'payment_method'   => 'cod',
        ])->assertStatus(403)
          ->assertJson(['error' => 'account_pending']);
    }
}