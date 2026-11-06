<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use Illuminate\Http\Request;

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
}