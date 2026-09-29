<?php

namespace Tests\Feature\Services\Import;

use App\Services\Import\RecipeFetchException;
use App\Services\Import\RecipeUrlImporter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecipeUrlImporterTest extends TestCase
{
    public function test_parses_recipe_json_ld_directly(): void
    {
        $html = $this->htmlWithJsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Test Pancakes',
            'image' => 'https://example.com/pancakes.jpg',
            'recipeYield' => '4 servings',
            'recipeIngredient' => ['2 cups flour', '1 cup milk', '2 eggs'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Mix dry ingredients.'],
                ['@type' => 'HowToStep', 'text' => 'Add wet ingredients and whisk.'],
            ],
        ]);

        Http::fake(['example.com/*' => Http::response($html, 200)]);

        $result = (new RecipeUrlImporter)->import('https://example.com/pancakes');

        $this->assertTrue($result->jsonLdFound);
        $this->assertSame('Test Pancakes', $result->title);
        $this->assertSame('https://example.com/pancakes.jpg', $result->imageUrl);
        $this->assertSame(4, $result->servings);
        $this->assertSame(['2 cups flour', '1 cup milk', '2 eggs'], $result->ingredientLines);
        $this->assertCount(2, $result->instructions);
    }

    public function test_parses_recipe_json_ld_inside_graph(): void
    {
        $html = $this->htmlWithJsonLd([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'WebSite', 'name' => 'Some Site'],
                [
                    '@type' => ['Recipe'],
                    'name' => 'Graph Recipe',
                    'recipeIngredient' => ['1 cup rice'],
                ],
            ],
        ]);

        Http::fake(['example.com/*' => Http::response($html, 200)]);

        $result = (new RecipeUrlImporter)->import('https://example.com/graph-recipe');

        $this->assertTrue($result->jsonLdFound);
        $this->assertSame('Graph Recipe', $result->title);
    }

    public function test_falls_back_to_title_and_og_image_when_no_json_ld(): void
    {
        $html = <<<'HTML'
            <html><head>
                <title>Some Blog Post About Cooking</title>
                <meta property="og:image" content="/images/cover.jpg">
            </head><body>No structured data here.</body></html>
            HTML;

        Http::fake(['example.com/*' => Http::response($html, 200)]);

        $result = (new RecipeUrlImporter)->import('https://example.com/blog-post');

        $this->assertFalse($result->jsonLdFound);
        $this->assertSame('Some Blog Post About Cooking', $result->title);
        $this->assertSame('https://example.com/images/cover.jpg', $result->imageUrl);
        $this->assertSame('no_recipe_json_ld_found', $result->failureReason);
    }

    public function test_falls_back_when_json_ld_recipe_has_no_ingredients(): void
    {
        $html = $this->htmlWithJsonLd([
            '@type' => 'Recipe',
            'name' => 'Empty Recipe',
        ]);

        Http::fake(['example.com/*' => Http::response($html, 200)]);

        $result = (new RecipeUrlImporter)->import('https://example.com/empty');

        $this->assertFalse($result->jsonLdFound);
        // A Recipe node with no ingredients is treated as unusable, so we
        // fall back to the plain <title> tag, not the JSON-LD name.
        $this->assertSame('Fallback Title', $result->title);
    }

    public function test_throws_on_http_failure(): void
    {
        Http::fake(['example.com/*' => Http::response('', 500)]);

        $this->expectException(RecipeFetchException::class);

        (new RecipeUrlImporter)->import('https://example.com/down');
    }

    private function htmlWithJsonLd(array $jsonLd): string
    {
        $json = json_encode($jsonLd);

        return <<<HTML
            <html><head>
                <title>Fallback Title</title>
                <script type="application/ld+json">{$json}</script>
            </head><body></body></html>
            HTML;
    }
}
