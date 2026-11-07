<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Order;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'exists:orders,id'],
            'subject'  => ['required', 'string', 'max:255'],
            'body'     => ['required', 'string', 'max:5000'],
        ]);

        // If an order_id was provided, ensure it belongs to this customer
        if (! empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
            if ($order && $order->customer_id !== $request->user()->id) {
                return response()->json(['error' => 'not_your_order'], 403);
            }
        }

        $complaint = Complaint::create([
            'customer_id' => $request->user()->id,
            'order_id'    => $data['order_id'] ?? null,
            'subject'     => $data['subject'],
            'body'        => $data['body'],
            'status'      => 'open',
        ]);

        return response()->json(['message' => 'Complaint submitted.', 'data' => $complaint], 201);
    }
}