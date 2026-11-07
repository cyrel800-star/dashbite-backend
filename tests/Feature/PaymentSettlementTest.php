<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DispatchAttempt;
use App\Models\Order;
use App\Models\PaymentRecord;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rider;
    private Order $order;
    private PaymentRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'           => UserRole::Admin,
            'account_status' => AccountStatus::Approved,
        ]);

        $this->rider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Approved,
        ]);

        $customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);

        $restaurant = Restaurant::factory()->create();

        $this->order = Order::create([
            'idempotency_key'  => 'pay-' . uniqid(),
            'customer_id'      => $customer->id,
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

        // Simulate rider was the winner of this order
        DispatchAttempt::create([
            'order_id'        => $this->order->id,
            'ring_km'         => 1,
            'status'          => 'assigned',
            'winner_rider_id' => $this->rider->id,
            'started_at'      => now(),
            'ended_at'        => now(),
        ]);

        // Create payment record
        $this->record = app(PaymentService::class)->recordCodCollected($this->order);

        Sanctum::actingAs($this->admin);
    }

    // ============================================
    // W6.04 AC #1 (part a): Rider → Admin COD remittance
    // ============================================
    public function test_admin_can_record_rider_remittance(): void
    {
        $this->postJson("/api/admin/payments/{$this->record->id}/remittance", [
            'amount' => 130.00,
            'notes'  => 'Daily remittance',
        ])->assertStatus(201)
          ->assertJsonPath('data.type', 'rider_remittance')
          ->assertJsonPath('data.amount', '130.00');

        $this->assertDatabaseHas('settlements', [
            'payment_record_id' => $this->record->id,
            'type'              => 'rider_remittance',
            'amount'            => 130.00,
        ]);
    }

    // ============================================
    // W6.04 AC #1 (part b): Admin → Restaurant payout
    // ============================================
    public function test_admin_can_record_restaurant_payout(): void
    {
        $this->postJson("/api/admin/payments/{$this->record->id}/payout", [
            'amount' => 90.00,
            'notes'  => 'Weekly payout',
        ])->assertStatus(201)
          ->assertJsonPath('data.type', 'restaurant_payout')
          ->assertJsonPath('data.amount', '90.00');

        $this->assertDatabaseHas('settlements', [
            'payment_record_id' => $this->record->id,
            'type'              => 'restaurant_payout',
            'amount'            => 90.00,
        ]);
    }

    // ============================================
    // AC #1 (part c): Remittance and payout remain separately traceable
    // ============================================
    public function test_remittance_and_payout_are_separately_traceable(): void
    {
        $this->postJson("/api/admin/payments/{$this->record->id}/remittance", ['amount' => 130.00]);
        $this->postJson("/api/admin/payments/{$this->record->id}/payout", ['amount' => 90.00]);

        $record = $this->record->fresh(['settlements']);
        $this->assertCount(2, $record->settlements);

        $remittance = $record->settlements->firstWhere('type', 'rider_remittance');
        $payout     = $record->settlements->firstWhere('type', 'restaurant_payout');

        $this->assertNotNull($remittance);
        $this->assertNotNull($payout);
        $this->assertEquals('130.00', $remittance->amount);
        $this->assertEquals('90.00', $payout->amount);
    }

    // ============================================
    // AC #2: Partial COD → remains flagged as incomplete (pending, not settled)
    // ============================================
    public function test_short_remittance_keeps_payment_incomplete(): void
    {
        $this->postJson("/api/admin/payments/{$this->record->id}/remittance", [
            'amount' => 100.00, // short by 30
        ])->assertStatus(201);

        $record = $this->record->fresh();
        $this->assertEquals(PaymentStatus::Collected, $record->status); // NOT settled
        $this->assertNull($record->settled_at);
    }

    public function test_full_remittance_and_payout_marks_payment_settled(): void
    {
        // Remittance full amount
        $this->postJson("/api/admin/payments/{$this->record->id}/remittance", ['amount' => 130.00]);
        // Payout restaurant share
        $this->postJson("/api/admin/payments/{$this->record->id}/payout", ['amount' => 90.00]);

        $record = $this->record->fresh();
        $this->assertEquals(PaymentStatus::Settled, $record->status);
        $this->assertNotNull($record->settled_at);
    }

    // ============================================
    // Authorization guards
    // ============================================
    public function test_non_admin_cannot_record_settlements(): void
    {
        Sanctum::actingAs($this->rider);

        $this->postJson("/api/admin/payments/{$this->record->id}/remittance", [
            'amount' => 130.00,
        ])->assertStatus(403);
    }
}