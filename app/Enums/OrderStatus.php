<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Rejected  = 'rejected';
    case Preparing = 'preparing';
    case Ready     = 'ready';
    case PickedUp  = 'picked_up';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case NoRider   = 'no_rider';
}