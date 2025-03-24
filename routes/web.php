<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear-log', function () {
    file_put_contents(storage_path('logs/laravel.log'), '');
    return 'Laravel log cleared!';
});

