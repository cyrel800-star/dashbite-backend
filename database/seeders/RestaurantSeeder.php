<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        // Active restaurants
        Restaurant::factory()->count(3)->create([
            'is_open'     => true,
            'is_disabled' => false,
        ]);

        // Temporarily closed
        Restaurant::factory()->create([
            'name'        => 'Closed Kitchen',
            'is_open'     => false,
            'is_disabled' => false,
        ]);

        // Admin-disabled
        Restaurant::factory()->create([
            'name'        => 'Disabled Kitchen',
            'is_open'     => true,
            'is_disabled' => true,
        ]);
    }
}