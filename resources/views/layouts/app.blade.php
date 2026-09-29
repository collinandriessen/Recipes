<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col">
        <header class="border-b border-gray-200 bg-white">
            <nav class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                <a href="{{ url('/') }}" class="font-semibold text-lg text-indigo-600">
                    {{ config('app.name', 'SaaS Corp') }}
                </a>

                <div class="flex items-center gap-4 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-indigo-600">
                            Dashboard
                        </a>
                        <a href="{{ route('recipes.library') }}" class="text-gray-700 hover:text-indigo-600">
                            Recipes
                        </a>
                        <span class="text-gray-400">|</span>
                        <span class="text-gray-500">{{ auth()->user()->email }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-gray-700 hover:text-indigo-600">
                                Log out
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 hover:text-indigo-600">
                            Log in
                        </a>
                        <a
                            href="{{ route('register') }}"
                            class="px-3 py-1.5 rounded bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            Sign up
                        </a>
                    @endauth
                </div>
            </nav>
        </header>

        <main class="flex-1">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
                {{ $slot }}
            </div>
        </main>

        <footer class="border-t border-gray-200 bg-white text-center text-xs text-gray-400 py-4">
            &copy; {{ date('Y') }} {{ config('app.name', 'SaaS Corp') }}
        </footer>
    </div>

    @livewireScripts
</body>
</html>
