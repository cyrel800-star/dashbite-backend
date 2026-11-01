<?php

namespace App\Models;

use App\Enums\OtpOutcome;
use Illuminate\Database\Eloquent\Model;

class OtpRequest extends Model
{
    protected $fillable = [
        'user_id', 'email', 'ip_address', 'outcome', 'requested_at',
    ];

    protected $casts = [
        'outcome'      => OtpOutcome::class,
        'requested_at' => 'datetime',
    ];
}
