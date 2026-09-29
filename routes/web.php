<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Dashboard;
use App\Livewire\Recipes\ImportFromUrl;
use App\Livewire\Recipes\ManualEntry;
use App\Livewire\Recipes\ShowRecipe;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/recipes/import', ImportFromUrl::class)->name('recipes.import');
    Route::get('/recipes/manual', ManualEntry::class)->name('recipes.manual-entry');
    Route::get('/recipes/{recipe}/manual', ManualEntry::class)->name('recipes.manual-entry.edit');
    Route::get('/recipes/{recipe}', ShowRecipe::class)->name('recipes.show');
});
