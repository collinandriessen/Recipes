<?php

namespace Tests\Feature\Services\Ingredients;

use App\Models\Ingredient;
use App\Services\Ingredients\IngredientFuzzyMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngredientFuzzyMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_exact_local_match_is_auto_accepted_without_fdc_call(): void
    {
        Ingredient::query()->create(['name' => 'all-purpose flour']);

        Http::fake(); // any HTTP call would fail this test via assertNothingSent.

        $result = app(IngredientFuzzyMatcher::class)->match('all-purpose flour');

        $this->assertSame('auto', $result['status']);
        $this->assertSame('all-purpose flour', $result['ingredient']->name);
        Http::assertNothingSent();
    }

    public function test_close_local_match_is_auto_accepted(): void
    {
        Ingredient::query()->create(['name' => 'granulated sugar']);

        Http::fake();

        $result = app(IngredientFuzzyMatcher::class)->match('granulated sugar,');

        $this->assertSame('auto', $result['status']);
        Http::assertNothingSent();
    }

    public function test_local_miss_falls_back_to_fdc_and_caches_new_ingredient(): void
    {
        Http::fake([
            '*/foods/search*' => Http::response([
                'foods' => [['fdcId' => 999, 'description' => 'Quinoa, uncooked', 'foodCategory' => 'Grains']],
            ], 200),
        ]);

        $result = app(IngredientFuzzyMatcher::class)->match('quinoa');

        $this->assertSame('auto', $result['status']);
        $this->assertSame(1.0, $result['confidence']);
        $this->assertSame('usda_fdc', $result['ingredient']->external_source);
        $this->assertSame('999', $result['ingredient']->external_food_id);

        $this->assertSame(1, Ingredient::query()->count());
    }

    public function test_fdc_unavailable_does_not_throw_and_returns_unmatched(): void
    {
        Http::fake([
            '*/foods/search*' => Http::response([], 503),
        ]);

        $result = app(IngredientFuzzyMatcher::class)->match('some rare ingredient xyz');

        $this->assertSame('unmatched', $result['status']);
        $this->assertNull($result['ingredient']);
    }

    public function test_suggest_returns_local_prefix_matches_for_autocomplete(): void
    {
        Ingredient::query()->create(['name' => 'chicken breast']);
        Ingredient::query()->create(['name' => 'chicken thigh']);
        Ingredient::query()->create(['name' => 'beef chuck']);

        Http::fake();

        $suggestions = app(IngredientFuzzyMatcher::class)->suggest('chick');

        $this->assertCount(2, $suggestions);
        Http::assertNothingSent();
    }
}
