<?php

namespace App\Services\Nutrition;

use App\Models\Recipe;

/**
 * Rolls up per-ingredient nutrients (as stored on IngredientNutrient, which
 * FDC reports per 100g) into recipe-level totals, using each
 * RecipeIngredient's parsed quantity/unit converted to grams.
 *
 * Unit conversion is intentionally approximate for MVP (architecture §3/§5
 * scope: nutrition is a helpful estimate shown next to the recipe, not a
 * clinical/labeling-grade calculation) — volume units use rough
 * kitchen-standard gram equivalents unless the ingredient has a measured
 * density, weight units convert exactly.
 */
class RecipeNutritionCalculator
{
    /** Grams per unit, for common kitchen volume/count units (rough averages). */
    private const GRAMS_PER_UNIT = [
        'g' => 1,
        'kg' => 1000,
        'oz' => 28.3495,
        'lb' => 453.592,
        'ml' => 1, // ~1g/ml for water-like liquids; density_g_per_ml overrides when known.
        'l' => 1000,
        'cup' => 240,
        'tbsp' => 15,
        'tsp' => 5,
        'pinch' => 0.36,
        'dash' => 0.6,
    ];

    /** Fallback grams for count-based units we can't convert precisely. */
    private const DEFAULT_COUNT_GRAMS = [
        'clove' => 3,
        'slice' => 25,
        'piece' => 50,
        'stick' => 113, // butter stick
        'can' => 400,
    ];

    /**
     * @return array{calories_kcal: float, protein_g: float, carbs_g: float, fat_g: float, stale: bool}
     */
    public function calculate(Recipe $recipe): array
    {
        $totals = ['calories_kcal' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];
        $stale = false;

        foreach ($recipe->ingredients as $line) {
            $ingredient = $line->ingredient;

            if ($ingredient === null || $line->quantity === null) {
                $stale = true;

                continue;
            }

            $nutrients = $ingredient->nutrients;

            if ($nutrients === null) {
                $stale = true;

                continue;
            }

            $grams = $this->toGrams((float) $line->quantity, $line->unit, $ingredient->density_g_per_ml);

            if ($grams === null) {
                $stale = true;

                continue;
            }

            $factor = $grams / 100; // FDC nutrients are per 100g.

            $totals['calories_kcal'] += (float) ($nutrients->calories_kcal ?? 0) * $factor;
            $totals['protein_g'] += (float) ($nutrients->protein_g ?? 0) * $factor;
            $totals['carbs_g'] += (float) ($nutrients->carbs_g ?? 0) * $factor;
            $totals['fat_g'] += (float) ($nutrients->fat_g ?? 0) * $factor;
        }

        $servings = max(1, $recipe->servings);

        return [
            'calories_kcal' => round($totals['calories_kcal'] / $servings, 1),
            'protein_g' => round($totals['protein_g'] / $servings, 1),
            'carbs_g' => round($totals['carbs_g'] / $servings, 1),
            'fat_g' => round($totals['fat_g'] / $servings, 1),
            'stale' => $stale,
        ];
    }

    private function toGrams(float $quantity, ?string $unit, ?float $densityGPerMl): ?float
    {
        if ($unit === null) {
            return null; // no unit at all — can't convert a bare count reliably.
        }

        if (in_array($unit, ['ml', 'l', 'cup', 'tbsp', 'tsp'], true) && $densityGPerMl !== null) {
            $ml = $quantity * (self::GRAMS_PER_UNIT[$unit] ?? 1);

            return $ml * $densityGPerMl;
        }

        if (isset(self::GRAMS_PER_UNIT[$unit])) {
            return $quantity * self::GRAMS_PER_UNIT[$unit];
        }

        if (isset(self::DEFAULT_COUNT_GRAMS[$unit])) {
            return $quantity * self::DEFAULT_COUNT_GRAMS[$unit];
        }

        return null;
    }
}
