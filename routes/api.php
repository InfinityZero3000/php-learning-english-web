<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', fn () => ['status' => 'ok', 'version' => 'v1']);
