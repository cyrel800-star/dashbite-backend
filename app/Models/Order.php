<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'idempotency_key',
        'customer_id',
        'restaurant_id',
	'cash_collected', 'cash_collected_at',
        'status',
        'payment_method',
        'subtotal',
        'delivery_fee',
	'source',                     
    	'external_customer_name',   
  	'external_customer_phone',
        'total',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'notes',
        'placed_at',
        'accepted_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status'         => OrderStatus::class,
        'payment_method' => PaymentMethod::class,
        'subtotal'       => 'decimal:2',
        'delivery_fee'   => 'decimal:2',
        'total'          => 'decimal:2',
        'placed_at'      => 'datetime',
	'cash_collected'    => 'decimal:2',
	'cash_collected_at' => 'datetime',
        'accepted_at'    => 'datetime',
        'delivered_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . strtoupper(Str::random(8));
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }
public function statusHistory()
{
    return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
}
public function paymentRecord()
{
    return $this->hasOne(PaymentRecord::class);
}
public function dispatchAttempts()
{
    return $this->hasMany(DispatchAttempt::class);
}
}