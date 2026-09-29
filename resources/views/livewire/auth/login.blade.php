<div>
    <h1 class="text-lg font-semibold mb-4 text-center">Log in</h1>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autocomplete="email"
                required
                autofocus
                class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="current-password"
                required
                class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input wire:model="remember" type="checkbox" class="rounded border-gray-300">
            Remember me
        </label>

        <button
            type="submit"
            class="w-full py-2 rounded bg-indigo-600 text-white font-medium hover:bg-indigo-700"
        >
            Log in
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-500">
        Don't have an account?
        <a href="{{ route('register') }}" wire:navigate class="text-indigo-600 hover:underline">Sign up</a>
    </p>
</div>
