<?php

use App\Http\Controllers\GenerationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GenerationController::class, 'index'])->name('home');
Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
Route::get('/generations/{generation}/files/{filename}', [GenerationController::class, 'download'])
    ->where('filename', 'preview\.png|scheme\.svg|scheme\.pdf|palette\.json|diagnostics\.json')
    ->name('generations.download');
