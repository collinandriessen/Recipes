<?php

namespace Tests\Feature\Services\Allergens;

use App\Models\Allergen;
use App\Models\Ingredient;
use App\Services\Allergens\AllergenMapper;
use Database\Seeders\AllergenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllergenMapperDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AllergenSeeder::class);
    }

    public function test_mapping_writes_ingredient_allergen_rows(): void
    {
        $ingredient = Ingredient::create(['name' => 'Whole Milk']);

        $result = (new AllergenMapper)->mapIngredient($ingredient);

        $this->assertCount(1, $result);
        $this->assertDatabaseHas('ingredient_allergens', [
            'ingredient_id' => $ingredient->id,
            'allergen_id' => Allergen::where('name', 'Milk')->value('id'),
            'confidence' => 'confirmed',
            'mapping_source' => 'keyword_rule',
        ]);
    }

    public function test_remapping_replaces_rule_derived_rows_without_touching_manual_curation(): void
    {
        $ingredient = Ingredient::create(['name' => 'Soy Sauce']);
        $mapper = new AllergenMapper;

        $mapper->mapIngredient($ingredient);
        $this->assertGreaterThan(0, $ingredient->allergens()->count());

        // Simulate a human manually adding a curated allergen tag that the
        // ruleset wouldn't produce on its own.
        $sesame = Allergen::where('name', 'Sesame')->firstOrFail();
        $ingredient->allergens()->attach($sesame->id, [
            'confidence' => 'may_contain',
            'mapping_source' => 'manual_curation',
        ]);

        // Re-running the mapper must not delete the manual row.
        $mapper->mapIngredient($ingredient);

        $this->assertDatabaseHas('ingredient_allergens', [
            'ingredient_id' => $ingredient->id,
            'allergen_id' => $sesame->id,
            'mapping_source' => 'manual_curation',
        ]);
    }

    public function test_ingredient_with_no_allergen_keywords_gets_no_rows(): void
    {
        $ingredient = Ingredient::create(['name' => 'Fresh Broccoli']);

        (new AllergenMapper)->mapIngredient($ingredient);

        $this->assertSame(0, $ingredient->allergens()->count());
    }

    public function test_console_command_maps_all_ingredients(): void
    {
        Ingredient::create(['name' => 'Cheddar Cheese']);
        Ingredient::create(['name' => 'Almond']);
        Ingredient::create(['name' => 'Water']);

        $this->artisan('allergens:map')
            ->assertSuccessful();

        $this->assertDatabaseCount('ingredient_allergens', 2);
    }
}
