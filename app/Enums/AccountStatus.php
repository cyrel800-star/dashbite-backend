<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Disabled = 'disabled';
}
