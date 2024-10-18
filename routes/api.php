<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\MastersController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/client', [MastersController::class, 'add_clients']);
    Route::post('/client', [MastersController::class, 'view_clients']);
    Route::post('/edit_client', [MastersController::class, 'update_clients']);
    Route::post('/client', [MastersController::class, 'delete_clients']);

    Route::post('/suppliers', [MastersController::class, 'add_add_suppliers']);
    Route::post('/suppliers', [MastersController::class, 'add_add_suppliers']);
    Route::post('/suppliers', [MastersController::class, 'add_add_suppliers']);
    Route::post('/suppliers', [MastersController::class, 'add_add_suppliers']);


    Route::post('/logout', [AuthController::class, 'logout']);
});
