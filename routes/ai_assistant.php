<?php

use App\Http\Controllers\Ai\AiAssistantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('ai-assistant')
    ->name('ai-assistant.')
    ->controller(AiAssistantController::class)
    ->group(function () {
        Route::post('/chat', 'chat')->middleware('throttle:15,1')->name('chat');
        Route::get('/conversations', 'conversations')->middleware('throttle:60,1')->name('conversations');
        Route::get('/conversations/{conversation}', 'show')->whereNumber('conversation')->middleware('throttle:60,1')->name('show');
        Route::delete('/conversations/{conversation}', 'destroy')->whereNumber('conversation')->middleware('throttle:30,1')->name('destroy');
    });
