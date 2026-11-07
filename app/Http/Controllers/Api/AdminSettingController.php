<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function __construct(private SettingService $settings)
    {
    }

    public function showSettings()
    {
        return response()->json([
            'data' => [
                'delivery_fee' => $this->settings->getDeliveryFee(),
            ],
        ]);
    }

    public function updateFee(Request $request)
    {
        $data = $request->validate([
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        $newFee = $this->settings->setDeliveryFee((float) $data['delivery_fee']);

        return response()->json([
            'message' => 'Delivery fee updated.',
            'data'    => ['delivery_fee' => $newFee],
        ]);
    }
}