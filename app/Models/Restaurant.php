<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'service_boundary',
        'is_open',
        'is_disabled',
    ];

    protected $casts = [
        'service_boundary' => 'array',
        'is_open'          => 'boolean',
        'is_disabled'      => 'boolean',
    ];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function staff()
    {
        return $this->hasMany(User::class);
    }

    // Scope: orderable = open AND not disabled
    public function scopeOrderable($query)
    {
        return $query->where('is_open', true)->where('is_disabled', false);
    }

    public function isOrderable(): bool
    {
        return $this->is_open && ! $this->is_disabled;
    }
}