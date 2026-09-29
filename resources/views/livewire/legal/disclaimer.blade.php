<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h1 class="text-xl font-semibold mb-4">Allergen and nutrition information disclaimer</h1>

        {{-- SAA-21 allergen-disclaimer-copy doc, variant #4 (footer/Terms full
             paragraph). Verbatim per Product Lead's copy; do not trim. --}}
        <p class="text-sm text-gray-700 leading-relaxed">
            RecipeFit uses an automated process to estimate nutrition values and identify likely
            allergens in recipes, based on ingredient names and USDA FoodData Central data. This
            process is heuristic and iterative: it is not performed or reviewed by a medical
            professional, dietitian, or accredited food-safety service, and it will not catch
            every allergen, cross-contamination risk, or ingredient substitution. Nutrition and
            allergen information on RecipeFit is provided for general informational and
            organizational purposes only and must not be treated as a substitute for reading
            actual ingredient labels, consulting a healthcare provider, or exercising your own
            judgment. You are solely responsible for verifying that any recipe is safe for your
            dietary needs, allergies, or medical conditions before preparing or consuming it.
            RecipeFit and SaaS Corp disclaim liability for any adverse reaction, illness, or harm
            resulting from reliance on automated allergen or nutrition information provided
            through the service.
        </p>

        <a href="{{ url()->previous() }}" class="inline-block mt-6 text-sm text-indigo-600 hover:underline">
            &larr; Back
        </a>
    </div>
</div>
