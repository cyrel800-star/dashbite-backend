<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request, int $orderId)
    {
        $order = Order::find($orderId);

        if (! $order) {
            return response()->json(['error' => 'order_not_found'], 404);
        }

        // W7.01 AC #3: Only the actual customer of the order can rate
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['error' => 'not_your_order'], 403);
        }

        if ($order->status !== OrderStatus::Delivered) {
            return response()->json(['error' => 'order_not_delivered'], 422);
        }

        if ($order->rating) {
            return response()->json(['error' => 'already_rated'], 409);
        }

        $data = $request->validate([
            'restaurant_rating'  => ['nullable', 'integer', 'between:1,5'],
            'rider_rating'       => ['nullable', 'integer', 'between:1,5'],
            'restaurant_comment' => ['nullable', 'string', 'max:1000'],
            'rider_comment'      => ['nullable', 'string', 'max:1000'],
        ]);

        // W7.01 AC #1: Separate ratings stored
        $riderId = $order->dispatchAttempts()->where('status', 'assigned')->first()?->winner_rider_id;

        $rating = Rating::create([
            'order_id'           => $order->id,
            'customer_id'        => $request->user()->id,
            'rider_id'           => $riderId,
            'restaurant_rating'  => $data['restaurant_rating'] ?? null,
            'rider_rating'       => $data['rider_rating'] ?? null,
            'restaurant_comment' => $data['restaurant_comment'] ?? null,
            'rider_comment'      => $data['rider_comment'] ?? null,
        ]);

        return response()->json(['message' => 'Rating submitted.', 'data' => $rating], 201);
    }
}