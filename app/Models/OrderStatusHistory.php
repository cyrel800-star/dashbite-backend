<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
        'meta',
    ];

    protected $casts = [
        'from_status' => OrderStatus::class,
        'to_status'   => OrderStatus::class,
        'meta'        => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}