<?php

namespace App\Services\Billing;

use App\Models\User;

/**
 * Architecture §6: simple free|paid tier check, deliberately dumb (no
 * billing integration yet — users.subscription_tier is hand-set for now).
 * Its only job is to be the ONE seam every macro/allergen-filter code path
 * checks, so wiring in real billing (Stripe webhook flips the column) later
 * is a drop-in change with zero call-site churn.
 *
 * Scope of the gate: ONLY the combined macro range + allergen exclusion
 * filter (the premium hook per the roadmap/pitch) is gated. Free-tier users
 * keep full access to search, tags, collections, meal planning and the
 * shopping list — this is not a "free users see a crippled app" gate.
 */
class FeatureGate
{
    /** @var array<int, string> */
    public const GATED_MACRO_KEYS = [
        'calorieMin', 'calorieMax',
        'proteinMin', 'proteinMax',
        'carbsMin', 'carbsMax',
        'fatMin', 'fatMax',
    ];

    /** @var array<int, string> */
    public const GATED_ALLERGEN_KEYS = [
        'excludedAllergenIds',
        'excludedCustomExclusionIds',
    ];

    public function __construct(private readonly ?User $user) {}

    public static function forUser(?User $user): self
    {
        return new self($user);
    }

    public function tier(): string
    {
        return $this->user?->subscription_tier ?? 'free';
    }

    public function isPaidTier(): bool
    {
        return $this->tier() === 'paid';
    }

    public function canUseMacroAllergenFilters(): bool
    {
        return $this->isPaidTier();
    }

    /**
     * No-ops the gated macro/allergen keys in a filter input array for
     * free-tier users; every other key (search, tagId, collectionId, ...)
     * passes through untouched. Safe to call on a partial array — it only
     * ever touches keys that are actually present.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function gateFilterInput(array $filters): array
    {
        if ($this->canUseMacroAllergenFilters()) {
            return $filters;
        }

        foreach (self::GATED_MACRO_KEYS as $key) {
            if (array_key_exists($key, $filters)) {
                $filters[$key] = null;
            }
        }

        foreach (self::GATED_ALLERGEN_KEYS as $key) {
            if (array_key_exists($key, $filters)) {
                $filters[$key] = [];
            }
        }

        return $filters;
    }

    /**
     * Whether a single Livewire public property name is part of the gated
     * surface — used by components to intercept a direct property write
     * (e.g. wire:model on a slider) rather than only gating on emit.
     */
    public function isGatedProperty(string $property): bool
    {
        return in_array($property, [...self::GATED_MACRO_KEYS, ...self::GATED_ALLERGEN_KEYS], true);
    }
}
