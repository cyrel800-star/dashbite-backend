<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = [
        'order_id', 'restaurant_rating', 'rider_rating',
        'restaurant_comment', 'rider_comment',
        'customer_id', 'rider_id',
    ];

    protected $casts = [
        'restaurant_rating' => 'integer',
        'rider_rating'      => 'integer',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function rider() { return $this->belongsTo(User::class, 'rider_id'); }
}