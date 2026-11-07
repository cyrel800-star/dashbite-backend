<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettlementType;
use App\Models\Order;
use App\Models\PaymentRecord;
use App\Models\Settlement;
use App\Models\User;

class PaymentService
{
    public const RIDER_CUT    = 20.00; // flat per delivery (MVP)
    public const PLATFORM_FEE = 10.00; // flat per delivery

    /**
     * Auto-create payment record when an order is delivered (COD).
     */
    public function recordCodCollected(Order $order): PaymentRecord
    {
        $existing = PaymentRecord::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        $riderCut        = self::RIDER_CUT;
        $platformFee     = self::PLATFORM_FEE;
        $restaurantShare = max(0, (float) $order->subtotal - $platformFee);

        return PaymentRecord::create([
            'order_id'         => $order->id,
            'method'           => PaymentMethod::Cod,
            'status'           => PaymentStatus::Collected,
            'amount'           => $order->total,
            'rider_cut'        => $riderCut,
            'platform_fee'     => $platformFee,
            'restaurant_share' => $restaurantShare,
            'collected_at'     => now(),
        ]);
    }

    /**
     * W6.04 AC #1: Rider → Admin COD remittance.
     */
    public function recordRiderRemittance(PaymentRecord $record, User $admin, float $amount, ?string $notes = null): Settlement
    {
        $rider = $record->order->dispatchAttempts()
            ->where('status', 'assigned')
            ->first()?->winner;

        return Settlement::create([
            'payment_record_id' => $record->id,
            'type'              => SettlementType::RiderRemittance,
            'from_user_id'      => $rider?->id,
            'amount'            => $amount,
            'status'            => 'completed',
            'recorded_by'       => $admin->id,
            'notes'             => $notes,
            'completed_at'      => now(),
        ]);
    }

    /**
     * W6.04 AC #1 & AC #2: Admin → Restaurant payout.
     */
    public function recordRestaurantPayout(PaymentRecord $record, User $admin, float $amount, ?string $notes = null): Settlement
    {
        $order = $record->order;

        $settlement = Settlement::create([
            'payment_record_id' => $record->id,
            'type'              => SettlementType::RestaurantPayout,
            'from_user_id'      => $admin->id,
            'to_restaurant_id'  => $order->restaurant_id,
            'amount'            => $amount,
            'status'            => 'completed',
            'recorded_by'       => $admin->id,
            'notes'             => $notes,
            'completed_at'      => now(),
        ]);

        // AC #2: short remittance or short payout → not marked as settled
        $remitted = (float) $record->settlements()
            ->where('type', SettlementType::RiderRemittance->value)
            ->where('status', 'completed')
            ->sum('amount');

        $payout = (float) $record->settlements()
            ->where('type', SettlementType::RestaurantPayout->value)
            ->where('status', 'completed')
            ->sum('amount');

        if ($remitted >= (float) $record->amount && $payout >= (float) $record->restaurant_share) {
            $record->update([
                'status'     => PaymentStatus::Settled,
                'settled_at' => now(),
            ]);
        }

        return $settlement;
    }
}