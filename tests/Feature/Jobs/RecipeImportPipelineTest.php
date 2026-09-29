<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ComputeAllergensJob;
use App\Jobs\ComputeNutritionJob;
use App\Jobs\ImportRecipeJob;
use App\Jobs\MatchIngredientsJob;
use App\Models\Allergen;
use App\Models\Recipe;
use App\Models\RecipeImportEvent;
use App\Models\User;
use App\Services\Import\RecipeFetchException;
use App\Services\Import\RecipeUrlImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * End-to-end pipeline: ImportRecipeJob -> MatchIngredientsJob ->
 * ComputeNutritionJob / ComputeAllergensJob, against Http::fake() for both
 * the source page and FDC.
 */
class RecipeImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_json_ld_import_creates_needs_review_recipe_and_logs_success_event(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'example.com/*' => Http::response($this->recipeHtml(), 200),
        ]);

        Queue::fake([MatchIngredientsJob::class]);

        (new ImportRecipeJob($user->id, 'https://example.com/pancakes'))->handle(
            app(RecipeUrlImporter::class)
        );

        $recipe = Recipe::query()->firstOrFail();

        $this->assertSame('Fluffy Pancakes', $recipe->title);
        $this->assertSame('needs_review', $recipe->import_status);
        $this->assertSame('url_import', $recipe->source_type);
        $this->assertCount(3, $recipe->ingredients);

        $event = RecipeImportEvent::query()->firstOrFail();
        $this->assertSame(RecipeImportEvent::OUTCOME_JSON_LD_SUCCESS, $event->outcome);
        $this->assertSame('example.com', $event->domain);
        $this->assertSame(3, $event->ingredient_lines_found);

        Queue::assertPushed(MatchIngredientsJob::class, fn ($job) => $job->recipeId === $recipe->id);
    }

    public function test_no_json_ld_routes_to_manual_with_title_and_image_only(): void
    {
        $user = User::factory()->create();

        $html = <<<'HTML'
            <html><head>
                <title>Grandma's Secret Stew</title>
                <meta property="og:image" content="https://example.com/stew.jpg">
            </head><body>Just prose, no structured data.</body></html>
            HTML;

        Http::fake(['example.com/*' => Http::response($html, 200)]);

        (new ImportRecipeJob($user->id, 'https://example.com/stew'))->handle(
            app(RecipeUrlImporter::class)
        );

        $recipe = Recipe::query()->firstOrFail();

        $this->assertSame("Grandma's Secret Stew", $recipe->title);
        $this->assertSame('manual', $recipe->import_status);
        $this->assertSame('https://example.com/stew.jpg', $recipe->photo_path);
        $this->assertCount(0, $recipe->ingredients);

        $event = RecipeImportEvent::query()->firstOrFail();
        $this->assertSame(RecipeImportEvent::OUTCOME_JSON_LD_ABSENT, $event->outcome);
    }

    public function test_fetch_failure_logs_event_and_creates_no_recipe(): void
    {
        $user = User::factory()->create();

        Http::fake(['example.com/*' => Http::response('', 503)]);

        try {
            (new ImportRecipeJob($user->id, 'https://example.com/down'))->handle(
                app(RecipeUrlImporter::class)
            );
            $this->fail('Expected RecipeFetchException to propagate for retry.');
        } catch (RecipeFetchException) {
            // expected — the job lets the queue retry transient fetch failures.
        }

        $this->assertSame(0, Recipe::query()->count());

        $event = RecipeImportEvent::query()->firstOrFail();
        $this->assertSame(RecipeImportEvent::OUTCOME_FETCH_FAILED, $event->outcome);
    }

    public function test_full_pipeline_matches_ingredients_computes_nutrition_and_allergens(): void
    {
        Allergen::query()->create(['name' => 'Milk', 'is_top9' => true]);

        $recipe = Recipe::factory()->for(User::factory())->create([
            'import_status' => 'needs_review',
            'servings' => 2,
        ]);

        $recipe->ingredients()->create([
            'raw_text' => '2 cups milk',
            'sort_order' => 0,
        ]);

        Http::fake([
            '*/foods/search*' => Http::response([
                'foods' => [[
                    'fdcId' => 555,
                    'description' => 'Milk, whole',
                    'foodCategory' => 'Dairy and Egg Products',
                ]],
            ], 200),
            '*/food/555*' => Http::response([
                'foodNutrients' => [
                    ['nutrient' => ['name' => 'Energy'], 'amount' => 61],
                    ['nutrient' => ['name' => 'Protein'], 'amount' => 3.2],
                    ['nutrient' => ['name' => 'Total lipid (fat)'], 'amount' => 3.3],
                    ['nutrient' => ['name' => 'Carbohydrate, by difference'], 'amount' => 4.8],
                ],
            ], 200),
        ]);

        Bus::dispatchSync(new MatchIngredientsJob($recipe->id));

        $recipe->refresh();
        $line = $recipe->ingredients->first();

        $this->assertNotNull($line->ingredient_id);
        $this->assertSame(2.0, (float) $line->quantity);
        $this->assertSame('cup', $line->unit);
        $this->assertSame('auto', $line->match_status);

        Bus::dispatchSync(new ComputeNutritionJob($recipe->id));
        Bus::dispatchSync(new ComputeAllergensJob($recipe->id));

        $recipe->refresh();
        $this->assertNotNull($recipe->nutrition);
        $this->assertGreaterThan(0, $recipe->nutrition->calories_kcal);

        $this->assertTrue($recipe->allergenFlags()->where('name', 'Milk')->exists());
    }

    private function recipeHtml(): string
    {
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Fluffy Pancakes',
            'image' => 'https://example.com/pancakes.jpg',
            'recipeYield' => '4',
            'recipeIngredient' => ['2 cups flour', '1 cup milk', '2 eggs'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Mix.'],
            ],
        ]);

        return <<<HTML
            <html><head>
                <title>Fluffy Pancakes Recipe</title>
                <script type="application/ld+json">{$jsonLd}</script>
            </head><body></body></html>
            HTML;
    }
}
