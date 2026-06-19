<?php

use App\Http\Controllers\HolmesChatWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Welcome to Skylogs!']);
});

Route::prefix('holmes-chat')
    ->controller(HolmesChatWebController::class)
    ->group(function () {
        Route::get('/', 'index')->name('holmes-chat.index');
        Route::post('/login', 'login')->name('holmes-chat.login');
        Route::post('/logout', 'logout')->name('holmes-chat.logout');
        Route::post('/send', 'send')
            ->middleware('holmesChatWebAuth')
            ->name('holmes-chat.send');
    });
