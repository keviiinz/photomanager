<?php

use App\Http\Controllers\GalleryZipDownloadController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
Route::get('media/{media}/descargar', [MediaController::class, 'download'])->name('media.download');

Route::livewire('g/{gallery}', 'pages::galleries.show')->name('galleries.show');

Route::middleware(['auth'])->group(function () {
    Route::post('galerias/{gallery}/descargar-seleccion', GalleryZipDownloadController::class)->name('galleries.download-selection');
});

Route::middleware(['auth', 'verified', 'role:photographer'])->group(function () {
    Route::livewire('galerias', 'pages::galleries.index')->name('galleries.index');
    Route::livewire('galerias/crear', 'pages::galleries.create')->name('galleries.create');
    Route::livewire('galerias/{gallery}/editar', 'pages::galleries.edit')->name('galleries.edit');
});

Route::middleware(['auth', 'verified', 'role:client'])->group(function () {
    Route::livewire('mis-galerias', 'pages::galleries.my')->name('galleries.my');
});
