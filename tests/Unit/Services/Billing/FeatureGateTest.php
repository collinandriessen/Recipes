<?php

namespace Tests\Unit\Services\Billing;

use App\Models\User;
use App\Services\Billing\FeatureGate;
use PHPUnit\Framework\TestCase;

/**
 * SAA-19: FeatureGate is intentionally dumb — a free|paid column check that
 * no-ops macro/allergen filter inputs for free-tier users. These tests are
 * pure unit tests (no DB) since the gate itself does no querying.
 */
class FeatureGateTest extends TestCase
{
    private function userWithTier(string $tier): User
    {
        $user = new User;
        $user->subscription_tier = $tier;

        return $user;
    }

    public function test_free_tier_cannot_use_macro_allergen_filters(): void
    {
        $gate = FeatureGate::forUser($this->userWithTier('free'));

        $this->assertFalse($gate->canUseMacroAllergenFilters());
        $this->assertSame('free', $gate->tier());
    }

    public function test_paid_tier_can_use_macro_allergen_filters(): void
    {
        $gate = FeatureGate::forUser($this->userWithTier('paid'));

        $this->assertTrue($gate->canUseMacroAllergenFilters());
    }

    public function test_null_user_defaults_to_free_tier(): void
    {
        $gate = FeatureGate::forUser(null);

        $this->assertSame('free', $gate->tier());
        $this->assertFalse($gate->canUseMacroAllergenFilters());
    }

    public function test_gate_filter_input_noops_macro_and_allergen_keys_for_free_tier(): void
    {
        $gate = FeatureGate::forUser($this->userWithTier('free'));

        $gated = $gate->gateFilterInput([
            'calorieMin' => 100,
            'calorieMax' => 500,
            'excludedAllergenIds' => [1, 2],
            'excludedCustomExclusionIds' => [3],
            'search' => 'chicken',
            'tagId' => 4,
        ]);

        $this->assertNull($gated['calorieMin']);
        $this->assertNull($gated['calorieMax']);
        $this->assertSame([], $gated['excludedAllergenIds']);
        $this->assertSame([], $gated['excludedCustomExclusionIds']);
        // Non-gated keys pass through untouched.
        $this->assertSame('chicken', $gated['search']);
        $this->assertSame(4, $gated['tagId']);
    }

    public function test_gate_filter_input_passes_through_untouched_for_paid_tier(): void
    {
        $gate = FeatureGate::forUser($this->userWithTier('paid'));

        $filters = [
            'calorieMin' => 100,
            'excludedAllergenIds' => [1, 2],
            'search' => 'chicken',
        ];

        $this->assertSame($filters, $gate->gateFilterInput($filters));
    }

    public function test_gate_filter_input_only_touches_present_keys(): void
    {
        $gate = FeatureGate::forUser($this->userWithTier('free'));

        $gated = $gate->gateFilterInput(['search' => 'chicken']);

        $this->assertSame(['search' => 'chicken'], $gated);
    }

    public function test_is_gated_property(): void
    {
        $gate = FeatureGate::forUser($this->userWithTier('free'));

        $this->assertTrue($gate->isGatedProperty('calorieMin'));
        $this->assertTrue($gate->isGatedProperty('excludedAllergenIds'));
        $this->assertFalse($gate->isGatedProperty('search'));
        $this->assertFalse($gate->isGatedProperty('tagId'));
    }
}
