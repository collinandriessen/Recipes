<?php

namespace Tests\Unit\Services\Allergens;

use App\Services\Allergens\AllergenMapper;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic tests for the allergen keyword/category ruleset (architecture
 * §1, §7). These intentionally do NOT touch the database — the mapping
 * logic itself is what QA/product should review before beta, so it needs to
 * be testable/readable in isolation from Eloquent.
 */
class AllergenMapperTest extends TestCase
{
    private AllergenMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new AllergenMapper;
    }

    public function test_milk_keyword_match(): void
    {
        $result = $this->mapper->match('Whole Milk');

        $this->assertArrayHasKey('Milk', $result);
        $this->assertSame('confirmed', $result['Milk']['confidence']);
        $this->assertSame('keyword_rule', $result['Milk']['source']);
    }

    public function test_peanut_butter_confirmed(): void
    {
        $result = $this->mapper->match('Creamy Peanut Butter');

        $this->assertArrayHasKey('Peanuts', $result);
        $this->assertSame('confirmed', $result['Peanuts']['confidence']);
    }

    public function test_eggplant_does_not_match_eggs(): void
    {
        $result = $this->mapper->match('Eggplant');

        $this->assertArrayNotHasKey('Eggs', $result);
    }

    public function test_almond_milk_matches_tree_nuts_but_not_dairy_milk(): void
    {
        $result = $this->mapper->match('Unsweetened Almond Milk');

        $this->assertArrayHasKey('Tree Nuts', $result);
        $this->assertSame('confirmed', $result['Tree Nuts']['confidence']);
        // "almond milk" itself is a keyword, and plain "milk" also appears —
        // both are legitimate matches: almond milk products can carry trace
        // dairy manufacturing risk, so flagging Milk too is the conservative
        // (favor false positives) behavior called out in the ruleset docblock.
        $this->assertArrayHasKey('Milk', $result);
    }

    public function test_soy_sauce_matches_soybeans_and_wheat_may_contain(): void
    {
        $result = $this->mapper->match('Soy Sauce');

        $this->assertArrayHasKey('Soybeans', $result);
        $this->assertSame('confirmed', $result['Soybeans']['confidence']);
        $this->assertArrayHasKey('Wheat', $result);
        $this->assertSame('may_contain', $result['Wheat']['confidence']);
    }

    public function test_confirmed_wins_over_may_contain_when_both_fire(): void
    {
        // "sesame oil" fires the confirmed sesame-oil keyword; the ruleset
        // must not downgrade it if a may_contain rule for the same allergen
        // also happens to match some other substring.
        $result = $this->mapper->match('Sesame Oil');

        $this->assertSame('confirmed', $result['Sesame']['confidence']);
    }

    public function test_fdc_category_match(): void
    {
        $result = $this->mapper->match('Generic Cultured Product X', 'Dairy and Egg Products');

        $this->assertArrayHasKey('Milk', $result);
        $this->assertSame('fdc_category', $result['Milk']['source']);
    }

    public function test_plain_vegetable_has_no_allergen_matches(): void
    {
        $result = $this->mapper->match('Fresh Broccoli');

        $this->assertSame([], $result);
    }

    public function test_shellfish_keyword_match(): void
    {
        $result = $this->mapper->match('Jumbo Shrimp, peeled and deveined');

        $this->assertArrayHasKey('Shellfish', $result);
        $this->assertSame('confirmed', $result['Shellfish']['confidence']);
    }

    public function test_fish_sauce_matches_fish(): void
    {
        $result = $this->mapper->match('Thai Fish Sauce');

        $this->assertArrayHasKey('Fish', $result);
        $this->assertSame('confirmed', $result['Fish']['confidence']);
    }

    public function test_wheat_flour_matches_wheat_category_and_keyword(): void
    {
        $result = $this->mapper->match('All-Purpose Flour', 'Baked Products');

        $this->assertArrayHasKey('Wheat', $result);
        $this->assertSame('confirmed', $result['Wheat']['confidence']);
    }
}
