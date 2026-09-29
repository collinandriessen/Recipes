<?php

namespace App\Services\Ingredients;

/**
 * Parses a raw recipe ingredient line ("2 1/2 cups all-purpose flour,
 * sifted") into quantity/unit/name/preparation-note parts.
 *
 * Deliberately a small regex-based parser, not a full NLP model — per
 * architecture §3 scope, ingredient parsing complexity lives here (and in
 * IngredientFuzzyMatcher), not in a bespoke recipe-HTML parser.
 */
class IngredientLineParser
{
    /**
     * Known unit tokens (singular + common plural/abbreviation variants),
     * normalized to a canonical unit string. Order doesn't matter; matching
     * is done via a compiled alternation on normalized tokens.
     */
    private const UNIT_ALIASES = [
        'cup' => 'cup', 'cups' => 'cup', 'c' => 'cup',
        'tablespoon' => 'tbsp', 'tablespoons' => 'tbsp', 'tbsp' => 'tbsp', 'tbsps' => 'tbsp', 'tbs' => 'tbsp',
        'teaspoon' => 'tsp', 'teaspoons' => 'tsp', 'tsp' => 'tsp', 'tsps' => 'tsp',
        'ounce' => 'oz', 'ounces' => 'oz', 'oz' => 'oz',
        'pound' => 'lb', 'pounds' => 'lb', 'lb' => 'lb', 'lbs' => 'lb',
        'gram' => 'g', 'grams' => 'g', 'g' => 'g',
        'kilogram' => 'kg', 'kilograms' => 'kg', 'kg' => 'kg',
        'milliliter' => 'ml', 'milliliters' => 'ml', 'ml' => 'ml',
        'liter' => 'l', 'liters' => 'l', 'l' => 'l',
        'pinch' => 'pinch', 'pinches' => 'pinch',
        'dash' => 'dash', 'dashes' => 'dash',
        'clove' => 'clove', 'cloves' => 'clove',
        'can' => 'can', 'cans' => 'can',
        'slice' => 'slice', 'slices' => 'slice',
        'piece' => 'piece', 'pieces' => 'piece',
        'stick' => 'stick', 'sticks' => 'stick',
    ];

    /**
     * Unicode vulgar fraction glyphs -> decimal string, so "1½" parses like "1 1/2".
     */
    private const VULGAR_FRACTIONS = [
        '¼' => '1/4', '½' => '1/2', '¾' => '3/4',
        '⅓' => '1/3', '⅔' => '2/3',
        '⅛' => '1/8', '⅜' => '3/8', '⅝' => '5/8', '⅞' => '7/8',
    ];

    public function parse(string $rawText): ParsedIngredientLine
    {
        $text = trim($rawText);
        $normalized = $this->normalizeFractionGlyphs($text);

        [$quantity, $remainder] = $this->extractQuantity($normalized);
        [$unit, $remainder] = $this->extractUnit($remainder);
        [$name, $preparationNote] = $this->splitNameAndPreparation($remainder);

        return new ParsedIngredientLine(
            rawText: $text,
            quantity: $quantity,
            unit: $unit,
            name: $name,
            preparationNote: $preparationNote,
        );
    }

    private function normalizeFractionGlyphs(string $text): string
    {
        return strtr($text, self::VULGAR_FRACTIONS);
    }

    /**
     * @return array{0: ?float, 1: string} [quantity, remaining text]
     */
    private function extractQuantity(string $text): array
    {
        $text = ltrim($text);

        // Ranges like "2-3" or "2 to 3": take the lower bound (conservative
        // for nutrition math; user can correct in needs_review).
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:-|to)\s*\d+(?:\.\d+)?\s*/i', $text, $m)) {
            return [(float) $m[1], substr($text, strlen($m[0]))];
        }

        // Mixed number: "1 1/2"
        if (preg_match('#^(\d+)\s+(\d+)/(\d+)\s*#', $text, $m)) {
            $value = (int) $m[1] + ((int) $m[2] / max(1, (int) $m[3]));

            return [$value, substr($text, strlen($m[0]))];
        }

        // Simple fraction: "1/2"
        if (preg_match('#^(\d+)/(\d+)\s*#', $text, $m)) {
            $value = (int) $m[1] / max(1, (int) $m[2]);

            return [$value, substr($text, strlen($m[0]))];
        }

        // Decimal or integer: "2", "2.5"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*/', $text, $m)) {
            return [(float) $m[1], substr($text, strlen($m[0]))];
        }

        return [null, $text];
    }

    /**
     * @return array{0: ?string, 1: string} [canonical unit, remaining text]
     */
    private function extractUnit(string $text): array
    {
        $text = ltrim($text);

        if (preg_match('/^([a-zA-Z]+)\.?\s+/', $text, $m)) {
            $token = strtolower($m[1]);

            if (isset(self::UNIT_ALIASES[$token])) {
                return [self::UNIT_ALIASES[$token], substr($text, strlen($m[0]))];
            }
        }

        return [null, $text];
    }

    /**
     * Split "chicken breast, diced" or "onion (finely chopped)" into a
     * clean ingredient name plus a preparation note, so the note doesn't
     * pollute fuzzy matching against the `ingredients` dictionary.
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitNameAndPreparation(string $text): array
    {
        $text = trim($text);

        // Parenthetical note: "onion (finely chopped)"
        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $text, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        // Comma-separated note: "chicken breast, diced and trimmed"
        if (str_contains($text, ',')) {
            [$name, $note] = array_map('trim', explode(',', $text, 2));

            if ($name !== '') {
                return [$name, $note !== '' ? $note : null];
            }
        }

        return [$text, null];
    }
}
