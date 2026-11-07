<?php

namespace App\Enums;

enum SettlementType: string
{
    case RiderRemittance  = 'rider_remittance';
    case RestaurantPayout = 'restaurant_payout';
}