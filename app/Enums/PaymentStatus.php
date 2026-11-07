<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending   = 'pending';
    case Collected = 'collected';
    case Settled   = 'settled';
    case Failed    = 'failed';
}