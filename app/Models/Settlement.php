<?php

namespace App\Models;

use App\Enums\SettlementType;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    protected $fillable = [
        'payment_record_id', 'type', 'from_user_id', 'to_restaurant_id',
        'amount', 'status', 'recorded_by', 'notes', 'completed_at',
    ];

    protected $casts = [
        'type'         => SettlementType::class,
        'amount'       => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function paymentRecord()
    {
        return $this->belongsTo(PaymentRecord::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toRestaurant()
    {
        return $this->belongsTo(Restaurant::class, 'to_restaurant_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}