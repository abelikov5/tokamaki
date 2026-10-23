<?php

use App\Http\Controllers\GenerationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::get('/kartina-po-nomeram-po-foto', [GenerationController::class, 'index'])->name('generator');
Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
Route::post('/generations/{generation}/edit', [GenerationController::class, 'edit'])->name('generations.edit');
Route::get('/generations/{generation}/files/{filename}', [GenerationController::class, 'download'])
    ->where('filename', 'preview\.png|scheme\.svg|scheme\.pdf|palette\.json|diagnostics\.json')
    ->name('generations.download');
