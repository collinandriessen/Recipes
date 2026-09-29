<?php

namespace App\Jobs;

use App\Models\Recipe;
use App\Services\Allergens\RecipeAllergenAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Architecture §5: rolls up ingredient-level allergen mappings into
 * recipe-level recipe_allergen_flags rows via RecipeAllergenAggregator.
 * Runs after MatchIngredientsJob so ingredient_id/allergen mappings exist;
 * safe to re-run any time (aggregator does a full sync, not an append).
 */
class ComputeAllergensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $recipeId,
    ) {}

    public function handle(RecipeAllergenAggregator $aggregator): void
    {
        $recipe = Recipe::query()->with('ingredients.ingredient.allergens')->find($this->recipeId);

        if ($recipe === null) {
            return;
        }

        $aggregator->apply($recipe);
    }
}
