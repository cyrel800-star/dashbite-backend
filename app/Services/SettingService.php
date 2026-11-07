<?php

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    public const DELIVERY_FEE_KEY = 'delivery_fee';

    public function getDeliveryFee(): float
    {
        return (float) Setting::get(self::DELIVERY_FEE_KEY, 30.00); // default 30
    }

    public function setDeliveryFee(float $fee): float
    {
        Setting::set(self::DELIVERY_FEE_KEY, $fee);
        return $fee;
    }
}