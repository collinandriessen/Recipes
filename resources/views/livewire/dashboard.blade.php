<div class="bg-white border border-gray-200 rounded-lg p-6">
    <h1 class="text-xl font-semibold mb-2">Welcome, {{ auth()->user()->name }}</h1>
    <p class="text-gray-600">
        You're signed in as <strong>{{ auth()->user()->email }}</strong>
        on the <strong>{{ auth()->user()->subscription_tier }}</strong> tier.
    </p>
    <p class="mt-4 text-sm text-gray-400">
        This is a placeholder dashboard shell. Recipe features land in Phase 2.1+.
    </p>
</div>
