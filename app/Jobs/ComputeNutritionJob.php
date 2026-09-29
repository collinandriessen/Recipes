<?php

namespace App\Jobs;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeNutrition;
use App\Services\Nutrition\IngredientNutrientFetcher;
use App\Services\Nutrition\RecipeNutritionCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Architecture §5: fetches/caches FDC macro data for each matched
 * ingredient, then rolls up recipe-level nutrition totals. FDC failures
 * never fail this job (IngredientNutrientFetcher swallows FdcException) —
 * missing/failed lookups just mark the recipe's nutrition `stale` so the UI
 * can show "estimate incomplete" instead of blocking the import.
 */
class ComputeNutritionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $recipeId,
    ) {}

    public function handle(
        IngredientNutrientFetcher $nutrientFetcher,
        RecipeNutritionCalculator $calculator,
    ): void {
        $recipe = Recipe::query()->with('ingredients.ingredient.nutrients')->find($this->recipeId);

        if ($recipe === null) {
            return;
        }

        $matchedIngredientIds = $recipe->ingredients->pluck('ingredient_id')->filter()->unique();

        Ingredient::query()
            ->whereIn('id', $matchedIngredientIds)
            ->get()
            ->each(fn (Ingredient $ingredient) => $nutrientFetcher->fetch($ingredient));

        $recipe->load('ingredients.ingredient.nutrients');

        $totals = $calculator->calculate($recipe);

        RecipeNutrition::query()->updateOrCreate(
            ['recipe_id' => $recipe->id],
            [
                'calories_kcal' => $totals['calories_kcal'],
                'protein_g' => $totals['protein_g'],
                'carbs_g' => $totals['carbs_g'],
                'fat_g' => $totals['fat_g'],
                'computed_at' => now(),
                'stale' => $totals['stale'],
            ],
        );
    }
}
