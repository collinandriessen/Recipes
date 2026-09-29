<?php

namespace App\Services\Allergens;

use App\Models\Allergen;
use App\Models\Ingredient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Applies {@see AllergenMappingRules} to a single ingredient and writes the
 * resulting `ingredient_allergens` rows.
 *
 * Called from the (Phase 2.2) MatchIngredientsJob after an ingredient is
 * newly matched/created, and available standalone for backfills/tests.
 * Idempotent: re-running against the same ingredient replaces its
 * rule-derived rows without touching any `manual_curation` rows a human has
 * added (those are a distinct mapping_source and this mapper never deletes
 * them).
 */
class AllergenMapper
{
    /**
     * @return Collection<int, array{allergen: string, confidence: string, source: string}>
     *                                                                                      the rows that were written (for logging/testing), not Eloquent models
     */
    public function mapIngredient(Ingredient $ingredient, ?string $fdcCategory = null): Collection
    {
        $name = self::normalize($ingredient->name);
        $matches = $this->match($name, $fdcCategory);

        // Wipe previous rule-derived rows for this ingredient (keyword_rule /
        // fdc_category) before writing fresh ones — but never touch rows a
        // human curated by hand.
        $ingredient->allergens()
            ->wherePivotIn('mapping_source', ['keyword_rule', 'fdc_category'])
            ->detach();

        $allergenIdsByName = Allergen::query()->pluck('id', 'name');

        foreach ($matches as $allergenName => $result) {
            $allergenId = $allergenIdsByName[$allergenName] ?? null;

            if ($allergenId === null) {
                // Ruleset references an allergen name that isn't seeded —
                // skip rather than fail the whole ingredient's mapping.
                continue;
            }

            $ingredient->allergens()->attach($allergenId, [
                'confidence' => $result['confidence'],
                'mapping_source' => $result['source'],
            ]);
        }

        return collect($matches)->map(fn (array $r, string $allergen) => [
            'allergen' => $allergen,
            'confidence' => $r['confidence'],
            'source' => $r['source'],
        ])->values();
    }

    /**
     * Pure matching logic, exposed separately from the DB write so it can be
     * unit tested against plain strings without touching the database (this
     * is the part QA/product review per architecture §7 cares about most).
     *
     * @return array<string, array{confidence: string, source: string}> allergen name => best match
     */
    public function match(string $ingredientName, ?string $fdcCategory = null): array
    {
        $name = self::normalize($ingredientName);
        $category = $fdcCategory !== null ? self::normalize($fdcCategory) : null;

        $results = [];

        foreach (AllergenMappingRules::rules() as $allergenName => $rules) {
            foreach ($rules as $rule) {
                $isMatch = match ($rule['type']) {
                    'keyword' => self::containsWord($name, self::normalize($rule['pattern'])),
                    'category' => $category !== null && Str::contains($category, self::normalize($rule['pattern'])),
                    default => false,
                };

                if (! $isMatch) {
                    continue;
                }

                $source = $rule['type'] === 'category' ? 'fdc_category' : 'keyword_rule';
                $existing = $results[$allergenName] ?? null;

                // "confirmed" always wins over "may_contain" if both fire.
                if ($existing === null || ($existing['confidence'] === 'may_contain' && $rule['confidence'] === 'confirmed')) {
                    $results[$allergenName] = [
                        'confidence' => $rule['confidence'],
                        'source' => $source,
                    ];
                }
            }
        }

        return $results;
    }

    private static function normalize(string $value): string
    {
        return Str::of($value)->lower()->trim()->value();
    }

    /**
     * Word-boundary-ish substring match: the keyword must appear as a whole
     * word (or phrase of whole words) inside the ingredient name, not as a
     * substring of an unrelated word — e.g. "egg" must not match "eggplant".
     */
    private static function containsWord(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        $pattern = '/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/i';

        return (bool) preg_match($pattern, $haystack);
    }
}
