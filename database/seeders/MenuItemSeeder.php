<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed menu items for orderable restaurants
        $restaurants = Restaurant::orderable()->get();

        foreach ($restaurants as $restaurant) {
            MenuItem::factory()->count(5)->create([
                'restaurant_id' => $restaurant->id,
                'is_available'  => true,
            ]);

            // Add one out-of-stock item per restaurant
            MenuItem::factory()->create([
                'restaurant_id' => $restaurant->id,
                'name'          => 'Sold Out Item',
                'is_available'  => false,
            ]);
        }
    }
}