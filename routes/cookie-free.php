<?php

use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Search routes (no auth required)
// Middleware to set cache-control public, maxage=1200 on all of these routes
Route::middleware(['cache.headers:public;max_age=1200'])->group(function () {
    Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
    Route::get('/episode/{episode}/audio', [SearchController::class, 'getEpisodeAudio'])
        ->where('episode', '[0-9]+')
        ->name('search.episode.audio');
});

// Search routes with rate limiting to protect against abuse and control OpenAI API costs
Route::middleware(['cache.headers:public;max_age=1200', 'throttle:60,1'])->group(function () {
    Route::get('/', [SearchController::class, 'index'])->name('search.index');
    Route::get('/episode/{episode}/segments', [SearchController::class, 'getEpisodeSegments'])
        ->where('episode', '[0-9]+')
        ->name('search.episode.segments');
});
