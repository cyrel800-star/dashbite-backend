<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RestaurantFactory extends Factory
{
    protected $model = \App\Models\Restaurant::class;

    public function definition(): array
    {
        return [
            'name'            => $this->faker->company() . ' Kitchen',
            'address'         => $this->faker->address(),
            'phone'           => $this->faker->numerify('09#########'),
            'service_boundary' => null,
            'is_open'         => true,
            'is_disabled'     => false,
        ];
    }
}