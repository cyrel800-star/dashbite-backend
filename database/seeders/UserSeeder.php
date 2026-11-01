<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Get first restaurant to link with restaurant user
        $mainRestaurant = Restaurant::first();

        $users = [
            ['name' => 'Customer Test', 'email' => 'customer@test.com', 'role' => UserRole::Customer, 'status' => AccountStatus::Approved, 'restaurant_id' => null],
            ['name' => 'Restaurant Test', 'email' => 'restaurant@test.com', 'role' => UserRole::Restaurant, 'status' => AccountStatus::Approved, 'restaurant_id' => $mainRestaurant?->id],
            ['name' => 'Rider Test', 'email' => 'rider@test.com', 'role' => UserRole::Rider, 'status' => AccountStatus::Approved, 'restaurant_id' => null],
            ['name' => 'Rider Pending', 'email' => 'rider.pending@test.com', 'role' => UserRole::Rider, 'status' => AccountStatus::Pending, 'restaurant_id' => null],
            ['name' => 'Restaurant Disabled', 'email' => 'restaurant.disabled@test.com', 'role' => UserRole::Restaurant, 'status' => AccountStatus::Disabled, 'restaurant_id' => null],
            ['name' => 'Admin Test', 'email' => 'admin@test.com', 'role' => UserRole::Admin, 'status' => AccountStatus::Approved, 'restaurant_id' => null],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => 'password',
                    'role' => $u['role'],
                    'account_status' => $u['status'],
                    'restaurant_id' => $u['restaurant_id'],
                ]
            );
        }
    }
}