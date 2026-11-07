<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentRecord;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function index(Request $request)
    {
        $records = PaymentRecord::with(['order.customer:id,name', 'order.restaurant:id,name'])
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($records);
    }

    public function show(int $id)
    {
        $record = PaymentRecord::with(['order', 'settlements'])->find($id);

        if (! $record) {
            return response()->json(['error' => 'payment_record_not_found'], 404);
        }

        return response()->json(['data' => $record]);
    }

    /**
     * W6.04 AC #1: Record rider → admin COD remittance.
     */
    public function recordRemittance(Request $request, int $id)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $record = PaymentRecord::find($id);
        if (! $record) {
            return response()->json(['error' => 'payment_record_not_found'], 404);
        }

        $settlement = $this->payments->recordRiderRemittance(
            $record, $request->user(), (float) $data['amount'], $data['notes'] ?? null
        );

        return response()->json(['message' => 'Rider remittance recorded.', 'data' => $settlement], 201);
    }

    /**
     * W6.04 AC #1: Record admin → restaurant payout.
     */
    public function recordPayout(Request $request, int $id)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $record = PaymentRecord::find($id);
        if (! $record) {
            return response()->json(['error' => 'payment_record_not_found'], 404);
        }

        $settlement = $this->payments->recordRestaurantPayout(
            $record, $request->user(), (float) $data['amount'], $data['notes'] ?? null
        );

        return response()->json(['message' => 'Restaurant payout recorded.', 'data' => $settlement], 201);
    }
}