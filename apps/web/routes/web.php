<?php

use App\Http\Controllers\CustomPosterRequestController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosterCatalogController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');
Route::get('/sitemap.xml', [PosterCatalogController::class, 'sitemap'])->name('sitemap');
Route::get('/postery', [PosterCatalogController::class, 'index'])->name('posters.index');
Route::view('/postery/po-foto', 'posters.custom')->name('posters.custom');
Route::post('/postery/po-foto', [CustomPosterRequestController::class, 'store'])->middleware('throttle:5,1')->name('posters.custom.store');
Route::get('/admin/custom-poster-requests/{customPosterRequest}/photo', [CustomPosterRequestController::class, 'photo'])->name('admin.custom-poster-requests.photo');
Route::get('/postery/{poster}', [PosterCatalogController::class, 'show'])->name('posters.show');
Route::get('/kartina-po-nomeram-po-foto', [GenerationController::class, 'index'])->name('generator');
Route::get('/edit/kartina-po-nomeram-po-foto', [GenerationController::class, 'editor'])->name('generator.editor');
Route::get('/kartina-po-nomeram-po-foto/proekt/{generation:resume_token}', [GenerationController::class, 'resume'])
    ->name('generations.resume');
Route::get('/edit/kartina-po-nomeram-po-foto/proekt/{generation:resume_token}', [GenerationController::class, 'editorResume'])
    ->name('generations.editor.resume');
Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
Route::get('/generations/{generation:resume_token}', [GenerationController::class, 'show'])->name('generations.show');
Route::post('/generations/{generation:resume_token}/edit', [GenerationController::class, 'edit'])->name('generations.edit');
Route::post('/generations/{generation:resume_token}/undo', [GenerationController::class, 'undo'])->name('generations.undo');
Route::post('/generations/{generation:resume_token}/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/generations/{generation:resume_token}/files/{filename}', [GenerationController::class, 'download'])
    ->where('filename', 'original\.png|preview\.png|scheme\.svg|scheme\.pdf|unlabeled\.png|palette\.json|diagnostics\.json')
    ->name('generations.download');
Route::get('/admin/generation-previews/{generation}', [GenerationController::class, 'adminPreview'])
    ->name('admin.generations.preview');
Route::get('/admin/orders/{order}/images/{kind}', [OrderController::class, 'image'])
    ->whereIn('kind', ['original', 'generated'])
    ->name('admin.orders.image');
