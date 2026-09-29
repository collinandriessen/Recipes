<?php

namespace Tests\Unit\Services\Ingredients;

use App\Services\Ingredients\IngredientLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IngredientLineParserTest extends TestCase
{
    #[DataProvider('lines')]
    public function test_parses_quantity_unit_and_name(
        string $raw,
        ?float $expectedQty,
        ?string $expectedUnit,
        string $expectedName,
        ?string $expectedNote,
    ): void {
        $parsed = (new IngredientLineParser)->parse($raw);

        $this->assertSame($expectedQty, $parsed->quantity);
        $this->assertSame($expectedUnit, $parsed->unit);
        $this->assertSame($expectedName, $parsed->name);
        $this->assertSame($expectedNote, $parsed->preparationNote);
    }

    public static function lines(): array
    {
        return [
            'simple' => ['2 cups flour', 2.0, 'cup', 'flour', null],
            'mixed fraction' => ['1 1/2 cups sugar', 1.5, 'cup', 'sugar', null],
            'unicode fraction' => ['½ cup milk', 0.5, 'cup', 'milk', null],
            'decimal' => ['0.5 tsp salt', 0.5, 'tsp', 'salt', null],
            'abbreviation' => ['3 tbsp olive oil', 3.0, 'tbsp', 'olive oil', null],
            'parenthetical note' => ['1 onion (finely chopped)', 1.0, null, 'onion', 'finely chopped'],
            'comma note' => ['2 chicken breasts, diced', 2.0, null, 'chicken breasts', 'diced'],
            'no quantity' => ['salt to taste', null, null, 'salt to taste', null],
            'range takes lower bound' => ['2-3 cloves garlic', 2.0, 'clove', 'garlic', null],
            'plural unit alias' => ['4 ounces cheddar', 4.0, 'oz', 'cheddar', null],
        ];
    }
}
