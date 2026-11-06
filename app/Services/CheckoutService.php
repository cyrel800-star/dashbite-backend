<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * Delivery fee (flat rate for MVP).
     */
    public const DELIVERY_FEE = 30.00;

    /**
     * Process checkout.
     *
     * @param  User    $customer
     * @param  array   $payload  { idempotency_key, restaurant_id, items: [{menu_item_id, quantity}], delivery_address, delivery_lat, delivery_lng, notes, payment_method }
     * @return Order
     * @throws ValidationException
     */
    public function checkout(User $customer, array $payload): Order
    {
        // W3.04 AC #3: Idempotency — if same key exists, return existing order
        $existing = Order::where('idempotency_key', $payload['idempotency_key'])->first();
        if ($existing) {
            if ($existing->customer_id !== $customer->id) {
                throw ValidationException::withMessages([
                    'idempotency_key' => ['This key is already used by another order.'],
                ]);
            }
            return $existing;
        }

        $restaurant = Restaurant::find($payload['restaurant_id']);
        if (! $restaurant || ! $restaurant->isOrderable()) {
            throw ValidationException::withMessages([
                'restaurant_id' => ['Restaurant is not available for ordering.'],
            ]);
        }

        // W3.04 AC #2: Service boundary check
        $this->assertWithinServiceBoundary($restaurant, $payload);

        // W3.04 AC #1: Validate items & compute subtotal (prevents stale price/availability)
        [$itemsData, $subtotal] = $this->buildItemsData($restaurant, $payload['items']);

        $deliveryFee = self::DELIVERY_FEE;
        $total = $subtotal + $deliveryFee;

        // Atomic: create order + items
        return DB::transaction(function () use ($customer, $restaurant, $payload, $itemsData, $subtotal, $deliveryFee, $total) {
            $order = Order::create([
                'idempotency_key'  => $payload['idempotency_key'],
                'customer_id'      => $customer->id,
                'restaurant_id'    => $restaurant->id,
                'status'           => OrderStatus::Pending,
                'payment_method'   => PaymentMethod::from($payload['payment_method'] ?? 'cod'),
                'subtotal'         => $subtotal,
                'delivery_fee'     => $deliveryFee,
                'total'            => $total,
                'delivery_address' => $payload['delivery_address'],
                'delivery_lat'     => $payload['delivery_lat'] ?? null,
                'delivery_lng'     => $payload['delivery_lng'] ?? null,
                'notes'            => $payload['notes'] ?? null,
                'placed_at'        => now(),
            ]);

            foreach ($itemsData as $row) {
                $order->items()->create($row);
            }

            return $order->fresh(['items', 'restaurant', 'customer']);
        });
    }

    /**
     * Validate each menu item, check availability, snapshot price/name.
     */
    private function buildItemsData(Restaurant $restaurant, array $itemsInput): array
    {
        if (empty($itemsInput)) {
            throw ValidationException::withMessages([
                'items' => ['Cart is empty.'],
            ]);
        }

        $ids = array_column($itemsInput, 'menu_item_id');
        $menuItems = MenuItem::where('restaurant_id', $restaurant->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0.0;

        foreach ($itemsInput as $row) {
            $item = $menuItems->get($row['menu_item_id']);

            if (! $item) {
                throw ValidationException::withMessages([
                    'items' => ["Menu item #{$row['menu_item_id']} does not belong to this restaurant."],
                ]);
            }

            if (! $item->is_available) {
                throw ValidationException::withMessages([
                    'items' => ["Item '{$item->name}' is out of stock."],
                ]);
            }

            $qty = max(1, (int) $row['quantity']);
            $lineTotal = round((float) $item->price * $qty, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'menu_item_id' => $item->id,
                'item_name'    => $item->name,
                'unit_price'   => $item->price,
                'quantity'     => $qty,
                'line_total'   => $lineTotal,
            ];
        }

        return [$lines, round($subtotal, 2)];
    }

    /**
     * W3.04 AC #2: Address must be within the restaurant's service boundary.
     * If restaurant has no boundary set, allow all (town-wide default).
     */
    private function assertWithinServiceBoundary(Restaurant $restaurant, array $payload): void
    {
        $boundary = $restaurant->service_boundary;
        if (empty($boundary)) {
            return; // No boundary configured — assume town-wide
        }

        $lat = $payload['delivery_lat'] ?? null;
        $lng = $payload['delivery_lng'] ?? null;

        if ($lat === null || $lng === null) {
            throw ValidationException::withMessages([
                'delivery_address' => ['Delivery coordinates required for boundary check.'],
            ]);
        }

        // Simple boundary: { lat_min, lat_max, lng_min, lng_max }
        if (isset($boundary['lat_min'])) {
            $inBounds = $lat >= $boundary['lat_min']
                     && $lat <= $boundary['lat_max']
                     && $lng >= $boundary['lng_min']
                     && $lng <= $boundary['lng_max'];

            if (! $inBounds) {
                throw ValidationException::withMessages([
                    'delivery_address' => ['Delivery address is outside the service area.'],
                ]);
            }
        }
    }
}