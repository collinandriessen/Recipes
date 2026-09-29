<?php

namespace App\Services\Ingredients;

class ParsedIngredientLine
{
    public function __construct(
        public readonly string $rawText,
        public readonly ?float $quantity,
        public readonly ?string $unit,
        public readonly string $name,
        public readonly ?string $preparationNote,
    ) {}
}
