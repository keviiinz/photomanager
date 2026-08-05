<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return match (true) {
            auth()->user()->isSuperadmin() => redirect()->route('admin.activity-log'),
            auth()->user()->isPhotographer() => redirect()->route('galleries.index'),
            default => redirect()->route('galleries.my'),
        };
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/galleries.php';
require __DIR__.'/admin.php';
