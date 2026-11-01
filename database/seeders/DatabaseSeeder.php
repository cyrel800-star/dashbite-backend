<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RestaurantSeeder::class,  // 1st — restaurants muna
            UserSeeder::class,        // 2nd — then users (may restaurant_id na)
            MenuItemSeeder::class,    // 3rd — menu items sa orderable restaurants
        ]);
    }
}