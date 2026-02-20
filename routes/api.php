<?php

use App\Http\Controllers\SyncImportController;
use Illuminate\Support\Facades\Route;

Route::post('/sync-healthcheck', [SyncImportController::class, 'healthcheck']);
Route::post('/import-sync', [SyncImportController::class, 'import']);
