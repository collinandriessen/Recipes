<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'external_source' => 'fdc',
            'external_food_id' => (string) $this->faker->unique()->randomNumber(6),
            'default_unit' => 'g',
            'density_g_per_ml' => null,
        ];
    }
}
