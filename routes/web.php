<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Demo route proving the Livewire scaffold is wired end-to-end.
// Remove once real product routes replace it.
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');
