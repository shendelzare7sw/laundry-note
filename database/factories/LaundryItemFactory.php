<?php

namespace Database\Factories;

use App\Models\LaundryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaundryItem>
 */
class LaundryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->randomElement(['Selimut', 'Sprei', 'Bed cover']),
            'jumlah' => 1,
            'berat_kg' => null,
            'biaya' => fake()->randomElement([10000, 15000, 20000]),
            'urutan' => 0,
        ];
    }
}
