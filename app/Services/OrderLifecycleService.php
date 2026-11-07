<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrderLifecycleService
{
    /**
     * Allowed transitions from each state.
     * Key = current, Value = array of allowed next states.
     */
    private const TRANSITIONS = [
        OrderStatus::Pending->value    => [OrderStatus::Accepted->value, OrderStatus::Rejected->value, OrderStatus::Cancelled->value],
        OrderStatus::Accepted->value   => [OrderStatus::Preparing->value, OrderStatus::Cancelled->value],
        OrderStatus::Preparing->value  => [OrderStatus::Ready->value, OrderStatus::Cancelled->value],
        OrderStatus::Ready->value      => [OrderStatus::PickedUp->value, OrderStatus::Cancelled->value],
        OrderStatus::PickedUp->value   => [OrderStatus::Delivered->value],
        OrderStatus::Rejected->value   => [],
        OrderStatus::Delivered->value  => [],
        OrderStatus::Cancelled->value  => [],
        OrderStatus::NoRider->value    => [OrderStatus::Cancelled->value],
    ];

    public function accept(Order $order, User $actor, ?int $prepMinutes = null): Order
    {
        $meta = $prepMinutes !== null ? ['prep_minutes' => $prepMinutes] : null;

        return $this->transition($order, OrderStatus::Accepted, $actor, null, $meta);
    }

    public function reject(Order $order, User $actor, string $reason): Order
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Rejection reason is required.'],
            ]);
        }

        // W4.01 AC #1: rejected → rider search must NOT start.
        // We log this and set state; dispatch service (W5/W6) checks status before searching.
        return $this->transition($order, OrderStatus::Rejected, $actor, $reason);
    }

    public function markPreparing(Order $order, User $actor): Order
    {
        return $this->transition($order, OrderStatus::Preparing, $actor);
    }

    public function markReady(Order $order, User $actor): Order
    {
        return $this->transition($order, OrderStatus::Ready, $actor);
    }

    /**
     * Core transition — validates and logs.
     * W4.01 AC #2: invalid / out-of-sequence transitions are rejected.
     */
    public function transition(
        Order $order,
        OrderStatus $to,
        ?User $actor,
        ?string $reason = null,
        ?array $meta = null
    ): Order {
        $from = $order->status;

        if ($from === $to) {
            throw ValidationException::withMessages([
                'status' => ["Order is already in '{$to->value}' state."],
            ]);
        }

        $allowed = self::TRANSITIONS[$from->value] ?? [];
        if (! in_array($to->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid transition: {$from->value} → {$to->value}."],
            ]);
        }

        $order->update([
            'status'       => $to,
            'accepted_at'  => $to === OrderStatus::Accepted  ? now() : $order->accepted_at,
            'delivered_at' => $to === OrderStatus::Delivered ? now() : $order->delivered_at,
            'cancelled_at' => $to === OrderStatus::Cancelled ? now() : $order->cancelled_at,
        ]);

        OrderStatusHistory::create([
            'order_id'    => $order->id,
            'actor_id'    => $actor?->id,
            'from_status' => $from,
            'to_status'   => $to,
            'reason'      => $reason,
            'meta'        => $meta,
        ]);

        return $order->fresh();
    }
    /**
     * W6.02: Rider confirms pickup from restaurant.
     */
    public function markPickedUp(Order $order, \App\Models\User $actor): Order
    {
        return $this->transition($order, OrderStatus::PickedUp, $actor);
    }

    /**
     * W6.02: Rider confirms delivery. If COD, cash_collected must be recorded.
     */
    public function markDelivered(Order $order, \App\Models\User $actor, ?float $cashCollected = null): Order
    {
        if ($order->payment_method === \App\Enums\PaymentMethod::Cod) {
            if ($cashCollected === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'cash_collected' => ['Cash amount is required for COD orders.'],
                ]);
            }
            $order->update([
                'cash_collected'    => $cashCollected,
                'cash_collected_at' => now(),
            ]);
        }

        return $this->transition($order, OrderStatus::Delivered, $actor);
    }
}