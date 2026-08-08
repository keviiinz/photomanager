<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
    Route::livewire('admin/historial', 'pages::admin.activity-log')->name('admin.activity-log');
    Route::livewire('admin/imagenes-inicio', 'pages::admin.home-images')->name('admin.home-images');
});
