<?php

use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\SearchController;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

// Only include these routes in the local and testing environments
if (app()->environment(['local', 'testing'])) {
    Route::middleware(['auth'])->group(function () {
        Route::redirect('settings', 'settings/profile');

        Route::get('settings/profile', Profile::class)->name('settings.profile');
        Route::get('settings/password', Password::class)->name('settings.password');
        Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
        Route::get('/episodes', [EpisodeController::class, 'index'])->name('episodes.index');
        Route::get('/episodes/{episode}', [EpisodeController::class, 'show'])->name('episodes.show');
        Route::put('/transcript-segments/{segment}', [EpisodeController::class, 'updateTranscript'])->name('transcript.update');
        Route::delete('/transcript-segments/{segment}', [EpisodeController::class, 'deleteTranscript'])->name('transcript.delete');
        Route::post('/episodes/{episode}/re-evaluate-embeddings', [EpisodeController::class, 'reEvaluateEmbeddings'])->name('episodes.re-evaluate-embeddings');
        Route::post('/episodes/{episode}/re-transcribe', [EpisodeController::class, 'reTranscribe'])->name('episodes.re-transcribe');
        Route::post('/episodes/{episode}/sync', [EpisodeController::class, 'syncEpisode'])->name('episodes.sync');
        Route::get('/episodes/{episode}/sync-status', [EpisodeController::class, 'getSyncStatus'])->name('episodes.sync-status');
        Route::get('/episodes/{episode}/embedding-progress', [EpisodeController::class, 'getEmbeddingProgress'])->name('episodes.embedding-progress');
        Route::delete('/episodes/{episode}', [EpisodeController::class, 'destroy'])->name('episodes.destroy');
        Route::post('/cache/clear', [SearchController::class, 'clearCache'])->name('search.cache.clear');
    });

    require __DIR__.'/auth.php';
}
