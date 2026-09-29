<?php

namespace App\Services\Import;

/**
 * Result of attempting to fetch + parse a recipe URL for schema.org JSON-LD
 * (architecture §3). Immutable value object passed between ImportRecipeJob
 * and the Livewire/controller layer that shows the "needs review" screen.
 */
class RecipeImportResult
{
    /**
     * @param  array<int, string>  $ingredientLines  Raw ingredient strings straight from JSON-LD (recipeIngredient).
     * @param  array<int, string>  $instructions  Ordered plain-text instruction steps.
     */
    public function __construct(
        public readonly bool $jsonLdFound,
        public readonly ?string $title = null,
        public readonly ?string $imageUrl = null,
        public readonly ?int $servings = null,
        public readonly array $ingredientLines = [],
        public readonly array $instructions = [],
        public readonly ?string $failureReason = null,
    ) {}

    public static function success(
        string $title,
        ?string $imageUrl,
        ?int $servings,
        array $ingredientLines,
        array $instructions,
    ): self {
        return new self(
            jsonLdFound: true,
            title: $title,
            imageUrl: $imageUrl,
            servings: $servings,
            ingredientLines: $ingredientLines,
            instructions: $instructions,
        );
    }

    /**
     * JSON-LD absent or unusable — caller falls back to <title>/OpenGraph
     * image only (architecture §3, explicit MVP scope: no HTML-scraping/NLP
     * parser fallback).
     */
    public static function fallback(?string $title, ?string $imageUrl, string $failureReason): self
    {
        return new self(
            jsonLdFound: false,
            title: $title,
            imageUrl: $imageUrl,
            failureReason: $failureReason,
        );
    }
}
