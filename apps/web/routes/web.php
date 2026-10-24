<?php

use App\Http\Controllers\GenerationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');
Route::get('/kartina-po-nomeram-po-foto', [GenerationController::class, 'index'])->name('generator');
Route::get('/kartina-po-nomeram-po-foto/proekt/{generation:resume_token}', [GenerationController::class, 'resume'])
    ->name('generations.resume');
Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
Route::get('/generations/{generation:resume_token}', [GenerationController::class, 'show'])->name('generations.show');
Route::post('/generations/{generation:resume_token}/edit', [GenerationController::class, 'edit'])->name('generations.edit');
Route::post('/generations/{generation:resume_token}/undo', [GenerationController::class, 'undo'])->name('generations.undo');
Route::get('/generations/{generation:resume_token}/files/{filename}', [GenerationController::class, 'download'])
    ->where('filename', 'preview\.png|scheme\.svg|scheme\.pdf|unlabeled\.png|palette\.json|diagnostics\.json')
    ->name('generations.download');
