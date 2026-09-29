<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'source_url' => null,
            'source_type' => 'manual',
            'servings' => $this->faker->numberBetween(1, 6),
            'photo_path' => null,
            'instructions' => null,
            'import_status' => 'manual',
        ];
    }
}
