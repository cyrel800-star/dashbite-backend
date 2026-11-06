<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkoutService)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'idempotency_key'  => ['required', 'string', 'max:255'],
            'restaurant_id'    => ['required', 'exists:restaurants,id'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'delivery_address' => ['required', 'string', 'max:500'],
            'delivery_lat'     => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng'     => ['nullable', 'numeric', 'between:-180,180'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'payment_method'   => ['nullable', 'in:cod,online'],
        ]);

        $order = $this->checkoutService->checkout($request->user(), $data);

        return response()->json([
            'message' => 'Order placed successfully.',
            'data'    => $order,
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $order = $request->user()->orders()->with(['items', 'restaurant'])->find($id);

        if (! $order) {
            return response()->json(['error' => 'order_not_found'], 404);
        }

        return response()->json(['data' => $order]);
    }
}