<?php

use App\Http\Controllers\AIAssistantController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', [AIAssistantController::class, 'chat'])->name('api.chat');
Route::post('/ai-assistant', [AIAssistantController::class, 'chat'])->name('api.ai-assistant');
