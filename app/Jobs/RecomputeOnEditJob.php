<?php

namespace App\Jobs;

use App\Models\Recipe;
use App\Services\Ingredients\IngredientFuzzyMatcher;
use App\Services\Ingredients\IngredientLineParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Architecture §5: fired whenever a user edits a recipe's ingredient lines
 * (during needs_review confirmation, manual-entry save, or a later edit) so
 * nutrition/allergen data never silently drifts from what's actually in the
 * recipe. Distinct from MatchIngredientsJob because edits typically touch a
 * handful of lines, not the whole set the initial import produced — but for
 * MVP simplicity this re-runs matching for any line missing a confident
 * match (status != auto) plus always recomputes nutrition/allergens for the
 * whole recipe, since totals are recipe-scoped anyway.
 */
class RecomputeOnEditJob implements ShouldQueue
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
    ): void {
        $recipe = Recipe::query()->with('ingredients')->find($this->recipeId);

        if ($recipe === null) {
            return;
        }

        foreach ($recipe->ingredients as $line) {
            if ($line->match_status === 'auto' && $line->ingredient_id !== null) {
                continue; // already confidently matched; user edit was elsewhere (e.g. quantity already parsed by hand).
            }

            $parsed = $lineParser->parse($line->raw_text);
            $matchResult = $matcher->match($parsed->name);

            $line->update([
                'quantity' => $line->quantity ?? $parsed->quantity,
                'unit' => $line->unit ?? $parsed->unit,
                'ingredient_id' => $matchResult['ingredient']?->id,
                'match_confidence' => $matchResult['confidence'] > 0 ? $matchResult['confidence'] : null,
                'match_status' => $matchResult['status'],
            ]);
        }

        ComputeNutritionJob::dispatch($recipe->id)->onQueue('imports');
        ComputeAllergensJob::dispatch($recipe->id)->onQueue('imports');
    }
}
