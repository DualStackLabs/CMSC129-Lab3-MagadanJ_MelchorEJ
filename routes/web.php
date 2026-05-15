<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\ChatBotController;

// 1. Redirect the homepage straight to your dashboard
Route::get('/', function () {
    return redirect('/entries');
});

Route::get('/chat', function () {
    return view('chat.index');
})->name('chat.index');
Route::get('/chat-history', [ChatBotController::class, 'history'])->name('chat.history');
Route::post('/chat-send', [ChatBotController::class, 'chat'])->name('chat.send');

// 2. The Magic MVC Route
// This single line automatically generates the routes for your index, create, store, edit, update, and destroy methods!
Route::get('/entries/trash', [EntryController::class, 'trash'])->name('entries.trash');
// Put this with your other custom routes
Route::post('/entries/{id}/restore', [EntryController::class, 'restore'])->name('entries.restore');
// Check the {id} parameter name matches
Route::delete('/entries/{id}/force', [EntryController::class, 'forceDelete'])->name('entries.forceDelete');
Route::resource('entries', App\Http\Controllers\EntryController::class);
