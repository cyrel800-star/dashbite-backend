<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = \App\Models\MenuItem::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => \App\Models\Restaurant::factory(),
            'name'          => $this->faker->randomElement([
                'Adobo', 'Sinigang', 'Kare-Kare', 'Lechon', 'Pancit',
                'Halo-Halo', 'Lumpia', 'Sisig', 'Bulalo', 'Bicol Express',
            ]),
            'description'   => $this->faker->sentence(6),
            'price'         => $this->faker->randomFloat(2, 50, 500),
            'is_available'  => true,
        ];
    }
}