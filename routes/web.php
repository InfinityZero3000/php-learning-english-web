<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::view('/admin', 'admin');
