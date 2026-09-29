<?php

namespace App\Services\Allergens;

/**
 * Keyword/category-based allergen mapping ruleset.
 *
 * Architecture doc §1: since we're FDC-only (no vendor allergen labels), we
 * derive an ingredient's allergens ourselves from:
 *   (a) FDC's own food-category taxonomy where it maps cleanly, and
 *   (b) a maintained keyword dictionary of allergen-bearing ingredient names.
 *
 * This is intentionally a plain-PHP, data-driven ruleset (not a service with
 * external dependencies) so it's trivial to unit test and to hand to a
 * non-engineer (QA / product) for review per architecture §7's recommended
 * "internal QA review pass on the ruleset before beta."
 *
 * Matching policy:
 *  - `confirmed` — the ingredient name/category is essentially always that
 *    allergen (e.g. "milk", "peanut butter").
 *  - `may_contain` — the ingredient is often, but not always, made with/from
 *    the allergen, or the keyword is a partial/ambiguous signal (e.g. "curry"
 *    may contain tree nuts in some regional recipes, "broth" may contain
 *    fish). We flag conservatively (favor false positives over false
 *    negatives) given the safety stakes called out in §7 — a user filtering
 *    out an allergen should not see a recipe that actually contains it.
 *
 * Keyword matching is applied to the ingredient's normalized name; category
 * matching is applied to the FDC `foodCategory` string when available. Both
 * are checked; an ingredient can be flagged by both, in which case the
 * highest-confidence result wins for that allergen.
 */
class AllergenMappingRules
{
    /**
     * Allergen key => list of rules. Each rule is
     * ['type' => 'keyword'|'category', 'pattern' => string (regex-safe substring,
     * matched case-insensitively against a word boundary), 'confidence' => 'confirmed'|'may_contain'].
     *
     * Allergen keys match the seeded `allergens.name` values exactly.
     *
     * @return array<string, array<int, array{type: string, pattern: string, confidence: string}>>
     */
    public static function rules(): array
    {
        return [
            'Milk' => [
                ...self::keyword([
                    'milk', 'buttermilk', 'cream', 'creme', 'crème', 'half and half', 'half-and-half',
                    'butter', 'ghee', 'cheese', 'cheddar', 'mozzarella', 'parmesan', 'ricotta',
                    'yogurt', 'yoghurt', 'whey', 'casein', 'custard', 'condensed milk', 'evaporated milk',
                    'ice cream', 'sour cream', 'mascarpone',
                ], 'confirmed'),
                ...self::keyword(['nonfat dry milk', 'milk powder', 'lactose', 'ghee butter'], 'confirmed'),
                ...self::keyword(['margarine', 'non-dairy creamer', 'nondairy creamer'], 'may_contain'),
                ...self::category(['Dairy and Egg Products'], 'confirmed'),
            ],

            'Eggs' => [
                ...self::keyword([
                    'egg', 'eggs', 'egg white', 'egg yolk', 'mayonnaise', 'mayo', 'meringue',
                    'albumin', 'albumen',
                ], 'confirmed'),
                ...self::keyword(['egg noodle', 'egg noodles'], 'confirmed'),
                ...self::keyword(['custard', 'aioli'], 'may_contain'),
            ],

            'Fish' => [
                ...self::keyword([
                    'anchovy', 'anchovies', 'salmon', 'tuna', 'cod', 'halibut', 'tilapia', 'trout',
                    'mackerel', 'sardine', 'sardines', 'bass', 'snapper', 'catfish', 'herring',
                    'fish sauce', 'fish stock', 'fish broth', 'bonito', 'fish paste', 'surimi',
                ], 'confirmed'),
                ...self::keyword(['worcestershire', 'caesar dressing', 'caesar salad'], 'may_contain'),
                ...self::category(['Finfish and Shellfish Products'], 'confirmed'),
            ],

            'Shellfish' => [
                ...self::keyword([
                    'shrimp', 'prawn', 'prawns', 'crab', 'lobster', 'crawfish', 'crayfish',
                    'scallop', 'scallops', 'clam', 'clams', 'mussel', 'mussels', 'oyster', 'oysters',
                    'squid', 'calamari', 'octopus', 'shellfish',
                ], 'confirmed'),
            ],

            'Tree Nuts' => [
                ...self::keyword([
                    'almond', 'walnut', 'cashew', 'pecan', 'pistachio', 'macadamia', 'hazelnut',
                    'brazil nut', 'brazil nuts', 'pine nut', 'pine nuts', 'chestnut',
                    'nutella', 'marzipan', 'praline', 'nut butter', 'nut milk', 'almond milk',
                    'cashew milk', 'walnut oil', 'hazelnut spread',
                ], 'confirmed'),
                ...self::keyword(['pesto', 'curry paste', 'baklava'], 'may_contain'),
            ],

            'Peanuts' => [
                ...self::keyword([
                    'peanut', 'peanuts', 'peanut butter', 'peanut oil', 'groundnut', 'groundnuts',
                ], 'confirmed'),
                ...self::keyword(['satay', 'pad thai', 'mole sauce'], 'may_contain'),
            ],

            'Wheat' => [
                ...self::keyword([
                    'wheat', 'flour', 'all-purpose flour', 'bread flour', 'whole wheat',
                    'pasta', 'noodle', 'noodles', 'spaghetti', 'macaroni', 'couscous', 'semolina',
                    'bulgur', 'farro', 'bread', 'breadcrumb', 'breadcrumbs', 'panko', 'cracker',
                    'crackers', 'tortilla', 'pastry', 'pita', 'seitan', 'gluten',
                ], 'confirmed'),
                ...self::keyword(['soy sauce', 'teriyaki sauce', 'roux'], 'may_contain'),
                ...self::category(['Baked Products', 'Cereal Grains and Pasta'], 'confirmed'),
            ],

            'Soybeans' => [
                ...self::keyword([
                    'soy', 'soybean', 'soybeans', 'soy sauce', 'tofu', 'tempeh', 'edamame',
                    'miso', 'soy milk', 'soy protein', 'textured vegetable protein', 'tvp',
                    'soy lecithin', 'soybean oil',
                ], 'confirmed'),
                ...self::keyword(['vegetable oil', 'teriyaki sauce'], 'may_contain'),
            ],

            'Sesame' => [
                ...self::keyword([
                    'sesame', 'sesame seed', 'sesame seeds', 'sesame oil', 'tahini', 'halva',
                    'benne', 'za\'atar', 'zaatar',
                ], 'confirmed'),
                ...self::keyword(['hummus', 'falafel'], 'may_contain'),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, array{type: string, pattern: string, confidence: string}>
     */
    private static function keyword(array $words, string $confidence): array
    {
        return array_map(
            fn (string $word) => ['type' => 'keyword', 'pattern' => $word, 'confidence' => $confidence],
            $words,
        );
    }

    /**
     * @param  array<int, string>  $categories
     * @return array<int, array{type: string, pattern: string, confidence: string}>
     */
    private static function category(array $categories, string $confidence): array
    {
        return array_map(
            fn (string $category) => ['type' => 'category', 'pattern' => $category, 'confidence' => $confidence],
            $categories,
        );
    }
}
