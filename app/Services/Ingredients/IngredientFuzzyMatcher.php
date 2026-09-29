<?php

namespace App\Services\Ingredients;

use App\Models\Ingredient;
use App\Services\Fdc\Exceptions\FdcException;
use App\Services\Fdc\FdcClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Matches a parsed ingredient name against the local `ingredients`
 * dictionary. On miss, searches FDC and creates a new local ingredient row
 * so the same name resolves instantly next time (this is the "match" half
 * of architecture §3's "fuzzy-match against ingredients table; FDC search +
 * cache on miss").
 *
 * Also backs the manual-entry autocomplete endpoint (local-only, no FDC
 * call, for fast keystroke-driven suggestions).
 */
class IngredientFuzzyMatcher
{
    /** Similarity below this is treated as no match at all. */
    private const MIN_CONFIDENCE = 0.35;

    /** Similarity at/above this is auto-accepted without FDC fallback. */
    private const AUTO_MATCH_CONFIDENCE = 0.82;

    public function __construct(
        private readonly FdcClient $fdc,
    ) {}

    /**
     * @return array{ingredient: ?Ingredient, confidence: float, status: string, fdc_category: ?string}
     *                                                                                                  status: auto | manual | unmatched
     *                                                                                                  fdc_category: FDC's raw `foodCategory` string for this
     *                                                                                                  match, when the match came from a fresh FDC lookup (SAA-23
     *                                                                                                  item 6) — null for local-dictionary matches/no match,
     *                                                                                                  since we don't persist FDC category on the Ingredient
     *                                                                                                  model. Callers (MatchIngredientsJob) must thread this
     *                                                                                                  through to AllergenMapper::mapIngredient()'s
     *                                                                                                  $fdcCategory param or the mapper's FDC-category rules
     *                                                                                                  (Milk/Fish/Wheat category matches) never fire in
     *                                                                                                  production.
     */
    public function match(string $rawName): array
    {
        $name = $this->normalize($rawName);

        if ($name === '') {
            return ['ingredient' => null, 'confidence' => 0.0, 'status' => 'unmatched', 'fdc_category' => null];
        }

        $best = $this->bestLocalMatch($name);

        if ($best !== null && $best['confidence'] >= self::AUTO_MATCH_CONFIDENCE) {
            return ['ingredient' => $best['ingredient'], 'confidence' => $best['confidence'], 'status' => 'auto', 'fdc_category' => null];
        }

        // No confident local match — try FDC and cache the result as a new
        // local ingredient so future lookups for this name are instant.
        $fromFdc = $this->matchViaFdc($name);

        if ($fromFdc !== null) {
            return [
                'ingredient' => $fromFdc['ingredient'],
                'confidence' => 1.0,
                'status' => 'auto',
                'fdc_category' => $fromFdc['fdc_category'],
            ];
        }

        if ($best !== null && $best['confidence'] >= self::MIN_CONFIDENCE) {
            // Plausible but not confident enough to auto-accept — surface
            // for user confirmation rather than silently guessing (same
            // "never silently trust parser output" principle as JSON-LD
            // import, since a wrong match can hide an allergen).
            return ['ingredient' => $best['ingredient'], 'confidence' => $best['confidence'], 'status' => 'manual', 'fdc_category' => null];
        }

        return ['ingredient' => null, 'confidence' => 0.0, 'status' => 'unmatched', 'fdc_category' => null];
    }

    /**
     * Local-only suggestions for the manual-entry autocomplete UI. No FDC
     * calls — must be fast enough to run per keystroke.
     *
     * @return array<int, Ingredient>
     */
    public function suggest(string $partialName, int $limit = 8): array
    {
        $name = $this->normalize($partialName);

        if ($name === '') {
            return [];
        }

        return Ingredient::query()
            ->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $name).'%')
            ->orderByRaw('LENGTH(name) asc')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return ?array{ingredient: Ingredient, confidence: float}
     */
    private function bestLocalMatch(string $name): ?array
    {
        // Pull a bounded candidate set with a cheap SQL prefilter, then
        // score precisely in PHP with similar_text — the ingredients table
        // is small enough for MVP that this avoids a fuzzy-search extension.
        $candidates = Ingredient::query()
            ->where('name', 'like', '%'.substr($name, 0, 4).'%')
            ->orWhere('name', 'like', $name.'%')
            ->limit(200)
            ->get();

        if ($candidates->isEmpty()) {
            $candidates = Ingredient::query()->limit(500)->get();
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $score = $this->similarity($name, $this->normalize($candidate->name));

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best !== null ? ['ingredient' => $best, 'confidence' => $bestScore] : null;
    }

    private function similarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);
        $textSimilarity = $percent / 100;

        // Blend with Levenshtein-derived similarity for short strings where
        // similar_text can overweight common substrings.
        $maxLen = max(strlen($a), strlen($b));
        $levSimilarity = $maxLen > 0 ? 1 - (levenshtein($a, $b) / $maxLen) : 0.0;

        return max($textSimilarity, $levSimilarity);
    }

    /**
     * @return ?array{ingredient: Ingredient, fdc_category: ?string}
     */
    private function matchViaFdc(string $name): ?array
    {
        try {
            $results = $this->fdc->searchFoods($name, pageSize: 1);
        } catch (FdcException $e) {
            // FDC calls must not fail the import (architecture §5) — log and
            // fall through to the "no match" path so the job can continue.
            Log::info('ingredient_match.fdc_unavailable', ['name' => $name, 'error' => $e->getMessage()]);

            return null;
        }

        $food = $results[0] ?? null;

        if ($food === null || empty($food['fdcId'])) {
            return null;
        }

        $ingredient = Ingredient::query()->firstOrCreate(
            [
                'external_source' => 'usda_fdc',
                'external_food_id' => (string) $food['fdcId'],
            ],
            [
                'name' => $food['description'] ?? $name,
            ],
        );

        // SAA-23 item 6: surface FDC's raw foodCategory string so the caller
        // can thread it into AllergenMapper::mapIngredient()'s $fdcCategory
        // param — without this, the mapper's category rules are unreachable
        // dead code in production.
        return ['ingredient' => $ingredient, 'fdc_category' => $food['foodCategory'] ?? null];
    }

    private function normalize(string $name): string
    {
        $name = Str::lower(trim($name));
        $name = preg_replace('/[^a-z0-9\s]/', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }
}
