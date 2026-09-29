<?php

namespace App\Services\Nutrition;

use App\Models\Ingredient;
use App\Models\IngredientNutrient;
use App\Services\Fdc\Exceptions\FdcException;
use App\Services\Fdc\FdcClient;
use Illuminate\Support\Facades\Log;

/**
 * Fetches + caches per-ingredient nutrient data from FDC (architecture §5).
 * FDC calls must not fail imports outright: on any FdcException we simply
 * leave/return null and let the caller mark nutrition `stale` rather than
 * failing the whole job.
 */
class IngredientNutrientFetcher
{
    public function __construct(
        private readonly FdcClient $fdc,
    ) {}

    public function fetch(Ingredient $ingredient): ?IngredientNutrient
    {
        $existing = $ingredient->nutrients;

        if ($existing !== null && $existing->fetched_at !== null
            && $existing->fetched_at->gt(now()->subDays(30))) {
            return $existing;
        }

        if ($ingredient->external_source !== 'usda_fdc' || $ingredient->external_food_id === null) {
            return $existing;
        }

        try {
            $food = $this->fdc->getFood($ingredient->external_food_id);
        } catch (FdcException $e) {
            Log::info('nutrition.fdc_unavailable', [
                'ingredient_id' => $ingredient->id,
                'error' => $e->getMessage(),
            ]);

            return $existing; // stale but present, or null
        }

        $nutrients = $this->extractMacros($food);

        return IngredientNutrient::query()->updateOrCreate(
            ['ingredient_id' => $ingredient->id],
            [
                ...$nutrients,
                'source' => 'usda_fdc',
                'fetched_at' => now(),
            ],
        );
    }

    /**
     * @return array{calories_kcal: ?float, protein_g: ?float, carbs_g: ?float, fat_g: ?float, fiber_g: ?float, sodium_mg: ?float}
     */
    private function extractMacros(array $food): array
    {
        $byName = collect($food['foodNutrients'] ?? [])
            ->mapWithKeys(function (array $n) {
                $name = $n['nutrient']['name'] ?? $n['nutrientName'] ?? null;
                $amount = $n['amount'] ?? $n['value'] ?? null;

                return $name !== null ? [$name => $amount] : [];
            });

        $find = fn (array $names) => collect($names)
            ->map(fn ($n) => $byName->get($n))
            ->first(fn ($v) => $v !== null);

        return [
            'calories_kcal' => $find(['Energy']),
            'protein_g' => $find(['Protein']),
            'carbs_g' => $find(['Carbohydrate, by difference']),
            'fat_g' => $find(['Total lipid (fat)']),
            'fiber_g' => $find(['Fiber, total dietary']),
            'sodium_mg' => $find(['Sodium, Na']),
        ];
    }
}
