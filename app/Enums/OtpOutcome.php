<?php

namespace App\Enums;

enum OtpOutcome: string
{
    case Sent      = 'sent';
    case Throttled = 'throttled';
}
