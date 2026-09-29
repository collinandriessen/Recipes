<?php

namespace App\Jobs;

use App\Models\Recipe;
use App\Services\Allergens\AllergenMapper;
use App\Services\Ingredients\IngredientFuzzyMatcher;
use App\Services\Ingredients\IngredientLineParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Architecture §5, second pipeline stage: for each RecipeIngredient with a
 * raw_text line, parse quantity/unit (IngredientLineParser) and fuzzy-match
 * the ingredient name against the `ingredients` dictionary (which itself
 * searches + caches from FDC on a local miss). Newly matched/created
 * ingredients get allergen-mapped immediately so downstream
 * ComputeAllergensJob has data to aggregate.
 *
 * Chains ComputeNutritionJob + ComputeAllergensJob once matching completes —
 * those can run in parallel since they read disjoint output columns.
 */
class MatchIngredientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $recipeId,
    ) {}

    public function handle(
        IngredientLineParser $lineParser,
        IngredientFuzzyMatcher $matcher,
        AllergenMapper $allergenMapper,
    ): void {
        $recipe = Recipe::query()->with('ingredients')->find($this->recipeId);

        if ($recipe === null) {
            return; // recipe deleted before the job ran.
        }

        foreach ($recipe->ingredients as $line) {
            $parsed = $lineParser->parse($line->raw_text);

            $matchResult = $matcher->match($parsed->name);
            $ingredient = $matchResult['ingredient'];

            if ($ingredient !== null && $ingredient->wasRecentlyCreated) {
                // SAA-23 item 6: thread FDC's foodCategory through so the
                // mapper's category rules (Milk/Fish/Wheat category matches)
                // actually run — previously this called mapIngredient()
                // with no $fdcCategory arg at all, making those rules dead
                // code in production.
                $allergenMapper->mapIngredient($ingredient, $matchResult['fdc_category']);
            }

            $line->update([
                'quantity' => $parsed->quantity,
                'unit' => $parsed->unit,
                'preparation_note' => $parsed->preparationNote,
                'ingredient_id' => $ingredient?->id,
                'match_confidence' => $matchResult['confidence'] > 0 ? $matchResult['confidence'] : null,
                'match_status' => $matchResult['status'],
            ]);
        }

        ComputeNutritionJob::dispatch($recipe->id)->onQueue('imports');
        ComputeAllergensJob::dispatch($recipe->id)->onQueue('imports');
    }
}
