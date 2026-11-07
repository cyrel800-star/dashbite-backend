<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class PaymentRecord extends Model
{
    protected $fillable = [
        'order_id', 'method', 'status',
        'amount', 'rider_cut', 'platform_fee', 'restaurant_share',
        'collected_at', 'settled_at',
    ];

    protected $casts = [
        'method'           => PaymentMethod::class,
        'status'           => PaymentStatus::class,
        'amount'           => 'decimal:2',
        'rider_cut'        => 'decimal:2',
        'platform_fee'     => 'decimal:2',
        'restaurant_share' => 'decimal:2',
        'collected_at'     => 'datetime',
        'settled_at'       => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }
}