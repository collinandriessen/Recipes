<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <a href="{{ url('/') }}" class="font-semibold text-xl text-indigo-600">
                {{ config('app.name', 'SaaS Corp') }}
            </a>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
