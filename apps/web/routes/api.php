<?php

use App\Http\Controllers\Api\PosterImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1/admin')
    ->middleware(['catalog.api', 'throttle:30,1'])
    ->group(function () {
        Route::post('/poster-imports', [PosterImportController::class, 'store']);
        Route::get('/poster-imports/{posterImport}', [PosterImportController::class, 'show']);
    });
