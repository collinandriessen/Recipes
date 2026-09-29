<?php

namespace App\Services\Allergens;

use App\Models\Recipe;

/**
 * Rolls up ingredient-level allergen mappings (from AllergenMapper /
 * ingredient_allergens) into recipe-level `recipe_allergen_flags` rows.
 * Confidence is the max across all ingredients carrying that allergen
 * (confirmed beats may_contain) since a recipe is only as "safe" as its
 * riskiest ingredient for a given allergen.
 */
class RecipeAllergenAggregator
{
    private const CONFIDENCE_RANK = ['may_contain' => 1, 'confirmed' => 2];

    /**
     * @return array<int, array{allergen_id: int, confidence: string}>
     */
    public function computeFlags(Recipe $recipe): array
    {
        $best = [];

        foreach ($recipe->ingredients as $line) {
            $ingredient = $line->ingredient;

            if ($ingredient === null) {
                continue;
            }

            foreach ($ingredient->allergens as $allergen) {
                $confidence = $allergen->pivot->confidence;
                $rank = self::CONFIDENCE_RANK[$confidence] ?? 0;

                if (! isset($best[$allergen->id]) || $rank > $best[$allergen->id]['rank']) {
                    $best[$allergen->id] = ['confidence' => $confidence, 'rank' => $rank];
                }
            }
        }

        return collect($best)
            ->map(fn (array $v, int $allergenId) => ['allergen_id' => $allergenId, 'confidence' => $v['confidence']])
            ->values()
            ->all();
    }

    public function apply(Recipe $recipe): void
    {
        $flags = $this->computeFlags($recipe);

        $recipe->allergenFlags()->sync(
            collect($flags)->mapWithKeys(fn (array $f) => [$f['allergen_id'] => ['confidence' => $f['confidence']]])->all()
        );
    }
}
