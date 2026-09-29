<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Recipe URL import (architecture §3): fetch the page, try schema.org
 * Recipe JSON-LD first. On success we get structured ingredient lines,
 * instructions, servings, image — but we NEVER silently trust it for
 * allergen-relevant data (the caller sets import_status = needs_review so a
 * human confirms/edits before anything is treated as final).
 *
 * On JSON-LD absence/failure we deliberately do NOT fall back to
 * HTML-scraping or an NLP parser — that's an explicit MVP scope decision in
 * the architecture doc. We only pull <title> and an OpenGraph image (both
 * trivial the same document/meta-tag extraction, not a "parser") and hand
 * off to manual entry.
 */
class RecipeUrlImporter
{
    private const MAX_REDIRECTS = 5;

    private const TIMEOUT_SECONDS = 12;

    public function import(string $url): RecipeImportResult
    {
        $html = $this->fetch($url);

        $jsonLd = $this->extractRecipeJsonLd($html);

        if ($jsonLd !== null) {
            $parsed = $this->tryParseJsonLd($jsonLd);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        // No usable JSON-LD Recipe node — fall back to title/OG-image only.
        return RecipeImportResult::fallback(
            title: $this->extractTitle($html),
            imageUrl: $this->extractOgImage($html, $url),
            failureReason: $jsonLd === null ? 'no_recipe_json_ld_found' : 'json_ld_present_but_unparseable',
        );
    }

    private function fetch(string $url): string
    {
        try {
            $response = Http::withHeaders([
                // Some recipe sites block bare/bot-looking UAs; identify
                // honestly as our bot rather than spoofing a browser.
                'User-Agent' => 'RecipeFitBot/1.0 (+https://recipefit.app/bot)',
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->withOptions(['allow_redirects' => ['max' => self::MAX_REDIRECTS]])
                ->get($url);
        } catch (\Throwable $e) {
            throw new RecipeFetchException("Failed to fetch {$url}: ".$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RecipeFetchException("Fetching {$url} returned HTTP {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Find the first <script type="application/ld+json"> block whose parsed
     * JSON contains (or nests) a node with @type Recipe. Returns the decoded
     * associative array for that node, or null if none found / all
     * unparseable.
     */
    private function extractRecipeJsonLd(string $html): ?array
    {
        if (! preg_match_all(
            '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
            $html,
            $matches
        )) {
            return null;
        }

        foreach ($matches[1] as $block) {
            $decoded = json_decode(trim($block), true);

            if (! is_array($decoded)) {
                continue;
            }

            $recipe = $this->findRecipeNode($decoded);

            if ($recipe !== null) {
                return $recipe;
            }
        }

        return null;
    }

    /**
     * JSON-LD Recipe nodes can appear directly, inside an @graph array, or
     * as a bare array of nodes. Walk the common shapes.
     */
    private function findRecipeNode(array $node): ?array
    {
        if ($this->isRecipeType($node['@type'] ?? null)) {
            return $node;
        }

        if (isset($node['@graph']) && is_array($node['@graph'])) {
            foreach ($node['@graph'] as $child) {
                if (is_array($child) && $this->isRecipeType($child['@type'] ?? null)) {
                    return $child;
                }
            }
        }

        // Bare list of nodes: [{ "@type": "Recipe", ... }, { ... }]
        if (array_is_list($node)) {
            foreach ($node as $child) {
                if (is_array($child)) {
                    $found = $this->findRecipeNode($child);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    private function isRecipeType(mixed $type): bool
    {
        if (is_string($type)) {
            return $type === 'Recipe';
        }

        if (is_array($type)) {
            return in_array('Recipe', $type, true);
        }

        return false;
    }

    private function tryParseJsonLd(array $node): ?RecipeImportResult
    {
        $title = $this->stringValue($node['name'] ?? null);
        $ingredientLines = $this->extractIngredientLines($node);

        if ($title === null || $ingredientLines === []) {
            // A Recipe node with no name or no ingredients isn't usable —
            // treat as absent so we route to manual entry rather than
            // creating a half-empty needs_review recipe.
            return null;
        }

        return RecipeImportResult::success(
            title: $title,
            imageUrl: $this->extractImageFromNode($node),
            servings: $this->extractServings($node),
            ingredientLines: $ingredientLines,
            instructions: $this->extractInstructions($node),
        );
    }

    /**
     * @return array<int, string>
     */
    private function extractIngredientLines(array $node): array
    {
        $raw = $node['recipeIngredient'] ?? $node['ingredients'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn ($line) => $this->stringValue($line))
            ->filter(fn ($line) => $line !== null && trim($line) !== '')
            ->map(fn ($line) => trim($line))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractInstructions(array $node): array
    {
        $raw = $node['recipeInstructions'] ?? null;

        if (is_string($raw)) {
            return [trim($raw)];
        }

        if (! is_array($raw)) {
            return [];
        }

        $steps = [];

        foreach ($raw as $item) {
            if (is_string($item)) {
                $steps[] = trim($item);

                continue;
            }

            if (is_array($item)) {
                // HowToStep / HowToSection both commonly carry `text`.
                if (isset($item['text']) && is_string($item['text'])) {
                    $steps[] = trim($item['text']);
                } elseif (isset($item['itemListElement']) && is_array($item['itemListElement'])) {
                    foreach ($item['itemListElement'] as $sub) {
                        if (is_array($sub) && isset($sub['text']) && is_string($sub['text'])) {
                            $steps[] = trim($sub['text']);
                        }
                    }
                }
            }
        }

        return array_values(array_filter($steps, fn ($s) => $s !== ''));
    }

    private function extractServings(array $node): ?int
    {
        $raw = $node['recipeYield'] ?? null;

        if (is_array($raw)) {
            $raw = $raw[0] ?? null;
        }

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && preg_match('/(\d+)/', $raw, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function extractImageFromNode(array $node): ?string
    {
        $raw = $node['image'] ?? null;

        if (is_string($raw)) {
            return $raw;
        }

        if (is_array($raw)) {
            // Could be a plain list of URLs, an ImageObject, or a list of ImageObjects.
            if (isset($raw['url']) && is_string($raw['url'])) {
                return $raw['url'];
            }

            $first = $raw[0] ?? null;

            if (is_string($first)) {
                return $first;
            }

            if (is_array($first) && isset($first['url']) && is_string($first['url'])) {
                return $first['url'];
            }
        }

        return null;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);

            return $title !== '' ? Str::limit($title, 255, '') : null;
        }

        return null;
    }

    private function extractOgImage(string $html, string $baseUrl): ?string
    {
        if (preg_match(
            '#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i',
            $html,
            $m
        )) {
            return $this->resolveUrl($m[1], $baseUrl);
        }

        // Some pages put content before property.
        if (preg_match(
            '#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']#i',
            $html,
            $m
        )) {
            return $this->resolveUrl($m[1], $baseUrl);
        }

        return null;
    }

    private function resolveUrl(string $maybeRelative, string $baseUrl): string
    {
        if (Str::startsWith($maybeRelative, ['http://', 'https://'])) {
            return $maybeRelative;
        }

        $base = parse_url($baseUrl);

        if (! $base || ! isset($base['scheme'], $base['host'])) {
            return $maybeRelative;
        }

        $origin = "{$base['scheme']}://{$base['host']}".(isset($base['port']) ? ':'.$base['port'] : '');

        return Str::startsWith($maybeRelative, '/')
            ? $origin.$maybeRelative
            : $origin.'/'.ltrim($maybeRelative, '/');
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        // JSON-LD sometimes nests name/text in an object ({"@value": "..."}).
        if (is_array($value) && isset($value['@value']) && is_string($value['@value'])) {
            return $value['@value'];
        }

        return null;
    }
}
