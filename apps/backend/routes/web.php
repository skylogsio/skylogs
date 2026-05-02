<?php

use Illuminate\Support\Facades\Route;


Route::get("/", function () {
    return response()->json(["message" => "Welcome to Skylogs!"]);
});

// Route::get('/', function () {
//    return view('welcome');
// });
