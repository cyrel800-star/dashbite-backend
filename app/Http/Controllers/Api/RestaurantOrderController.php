<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use Illuminate\Http\Request;
use App\Enums\PaymentMethod;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestaurantOrderController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle)
    {
    }

    private function ownRestaurantId(Request $request): int
    {
        $id = $request->user()->restaurant_id;
        abort_if($id === null, 403, 'No restaurant linked to this account.');
        return $id;
    }

    private function findOwnOrder(Request $request, int $id): Order
    {
        $order = Order::where('restaurant_id', $this->ownRestaurantId($request))->find($id);

        if (! $order) {
            abort(response()->json(['error' => 'order_not_found'], 404));
        }

        return $order;
    }

    public function index(Request $request)
    {
        $query = Order::where('restaurant_id', $this->ownRestaurantId($request))
            ->with(['items', 'customer:id,name'])
            ->orderByDesc('placed_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20);

        return response()->json($orders);
    }

    public function show(Request $request, int $id)
    {
        $order = $this->findOwnOrder($request, $id)->load(['items', 'customer:id,name', 'statusHistory']);

        return response()->json(['data' => $order]);
    }

    public function accept(Request $request, int $id)
    {
        $data = $request->validate([
            'prep_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);

        $order = $this->findOwnOrder($request, $id);
        $order = $this->lifecycle->accept($order, $request->user(), $data['prep_minutes'] ?? null);

        return response()->json(['message' => 'Order accepted.', 'data' => $order]);
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $order = $this->findOwnOrder($request, $id);
        $order = $this->lifecycle->reject($order, $request->user(), $data['reason']);

        return response()->json(['message' => 'Order rejected.', 'data' => $order]);
    }

    public function markPreparing(Request $request, int $id)
    {
        $order = $this->findOwnOrder($request, $id);
        $order = $this->lifecycle->markPreparing($order, $request->user());

        return response()->json(['message' => 'Order marked as preparing.', 'data' => $order]);
    }

    public function markReady(Request $request, int $id)
    {
        $order = $this->findOwnOrder($request, $id);
        $order = $this->lifecycle->markReady($order, $request->user());

        return response()->json(['message' => 'Order marked as ready.', 'data' => $order]);
    }
    /**
     * W4.03: Restaurant-entered (phone/message/walk-in) order.
     * AC #1: Requires external customer name, phone, and delivery address.
     * AC #2: Follows same lifecycle (status = pending, restaurant decision flow).
     */
    public function storeExternal(Request $request)
    {
        $data = $request->validate([
            'source'                  => ['required', 'in:phone,message,walk_in'],
            'external_customer_name'  => ['required', 'string', 'max:255'],
            'external_customer_phone' => ['required', 'string', 'max:30'],
            'delivery_address'        => ['required', 'string', 'max:500'],
            'delivery_lat'            => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng'            => ['nullable', 'numeric', 'between:-180,180'],
            'payment_method'          => ['required', 'in:cod,online'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.menu_item_id'    => ['required', 'exists:menu_items,id'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ]);

        $restaurantId = $this->ownRestaurantId($request);
        $restaurant = $request->user()->restaurant;

        // Build line items — validate they belong to this restaurant & are available
        $ids = array_column($data['items'], 'menu_item_id');
        $menuItems = MenuItem::where('restaurant_id', $restaurantId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0.0;

        foreach ($data['items'] as $row) {
            $item = $menuItems->get($row['menu_item_id']);
            if (! $item) {
                throw ValidationException::withMessages([
                    'items' => ["Item #{$row['menu_item_id']} does not belong to your restaurant."],
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

        $deliveryFee = \App\Services\CheckoutService::DELIVERY_FEE;
        $total = round($subtotal + $deliveryFee, 2);

        // W4.03 AC #1: If delivery address missing, reject — no partial order
        if (trim($data['delivery_address']) === '') {
            throw ValidationException::withMessages([
                'delivery_address' => ['Delivery address is required.'],
            ]);
        }

        $order = DB::transaction(function () use (
            $request, $restaurant, $restaurantId, $data, $lines, $subtotal, $deliveryFee, $total
        ) {
            // Find or create a guest customer record (so orders.customer_id is not null)
            $customer = User::firstOrCreate(
                ['email' => 'guest+' . Str::random(12) . '@dashbite.local'],
                [
                    'name'           => $data['external_customer_name'],
                    'phone'          => $data['external_customer_phone'],
                    'password'       => bcrypt(Str::random(32)),
                    'role'           => \App\Enums\UserRole::Customer,
                    'account_status' => \App\Enums\AccountStatus::Approved,
                ]
            );

            $order = \App\Models\Order::create([
                'idempotency_key'         => 'rest-' . $restaurantId . '-' . Str::random(16),
                'customer_id'             => $customer->id,
                'restaurant_id'           => $restaurantId,
                'status'                  => OrderStatus::Pending,
                'payment_method'          => PaymentMethod::from($data['payment_method']),
                'source'                  => $data['source'],
                'external_customer_name'  => $data['external_customer_name'],
                'external_customer_phone' => $data['external_customer_phone'],
                'subtotal'                => $subtotal,
                'delivery_fee'            => $deliveryFee,
                'total'                   => $total,
                'delivery_address'        => $data['delivery_address'],
                'delivery_lat'            => $data['delivery_lat'] ?? null,
                'delivery_lng'            => $data['delivery_lng'] ?? null,
                'notes'                   => $data['notes'] ?? null,
                'placed_at'               => now(),
            ]);

            foreach ($lines as $row) {
                $order->items()->create($row);
            }

            \App\Models\OrderStatusHistory::create([
                'order_id'    => $order->id,
                'actor_id'    => $request->user()->id,
                'from_status' => null,
                'to_status'   => OrderStatus::Pending,
                'reason'      => 'External order via ' . $data['source'],
            ]);

            return $order->fresh(['items', 'restaurant', 'customer']);
        });

        return response()->json([
            'message' => 'External order created.',
            'data'    => $order,
        ], 201);
    }
}