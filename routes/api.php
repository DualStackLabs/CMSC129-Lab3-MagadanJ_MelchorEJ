<?php

use App\Http\Controllers\AIAssistantController;
use App\Http\Controllers\AIJournalToolController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', [AIAssistantController::class, 'chat'])
    ->middleware('throttle:20,1')
    ->name('api.chat');
Route::post('/ai-assistant', [AIAssistantController::class, 'chat'])
    ->middleware('throttle:20,1')
    ->name('api.ai-assistant');

Route::prefix('ai-tools')->name('ai-tools.')->group(function () {
    Route::get('/entries/context', [AIJournalToolController::class, 'context'])->name('entries.context');
    Route::get('/entries/facts', [AIJournalToolController::class, 'facts'])->name('entries.facts');
    Route::get('/entries/resolve', [AIJournalToolController::class, 'resolve'])->name('entries.resolve');
    Route::post('/entries', [AIJournalToolController::class, 'store'])->name('entries.store');
    Route::patch('/entries/{entry}', [AIJournalToolController::class, 'update'])->name('entries.update');
    Route::delete('/entries/{entry}', [AIJournalToolController::class, 'destroy'])->name('entries.destroy');
    Route::get('/categories', [AIJournalToolController::class, 'categories'])->name('categories.index');
});
