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
    Route::get('/client', [MastersController::class, 'view_clients']);
    Route::post('/update_client/{id}', [MastersController::class, 'update_clients']);
    Route::delete('/client/{id}', [MastersController::class, 'delete_clients']);

    Route::post('/suppliers', [MastersController::class, 'add_suppliers']);
    Route::get('/suppliers', [MastersController::class, 'view_suppliers']);
    Route::post('/update_suppliers/{id}', [MastersController::class, 'update_suppliers']);
    Route::delete('/suppliers/{id}', [MastersController::class, 'delete_supplier']);

    Route::post('/products', [MastersController::class, 'add_products']);
    Route::get('/products', [MastersController::class, 'view_products']);
    Route::post('/update_products/{id}', [MastersController::class, 'edit_products']);
    Route::delete('/products/{id}', [MastersController::class, 'delete_products']);

    Route::post('/year', [MastersController::class, 'add_f_year']);
    Route::get('/year', [MastersController::class, 'view_f_year']);
    Route::post('/update_year/{id}', [MastersController::class, 'edit_f_year']);
    Route::delete('/year/{id}', [MastersController::class, 'delete_f_year']);

    Route::post('/godown', [MastersController::class, 'add_godown']);
    Route::get('/godown', [MastersController::class, 'view_godown']);
    Route::post('/update_godown/{id?}', [MastersController::class, 'edit_godown']);
    Route::delete('/godown', [MastersController::class, 'delete_godown']);

    Route::post('/category', [MastersController::class, 'add_category']);
    Route::get('/pdf', [MastersController::class, 'view_category']);
    Route::post('/update_category/{id?}', [MastersController::class, 'edit_category']);
    Route::delete('/pdf', [MastersController::class, 'delete_category']);

    Route::post('/sub_category', [MastersController::class, 'add_sub_category']);
    Route::get('/pdf', [MastersController::class, 'sub_category']);
    Route::post('/update_pdf/{id?}', [MastersController::class, 'edit_sub_category']);
    Route::delete('/pdf', [MastersController::class, 'delete_sub_category']);

    Route::post('/brand', [MastersController::class, 'add_brand']);
    Route::get('/brand', [MastersController::class, 'brand']);
    Route::post('/update_brand/{id?}', [MastersController::class, 'edit_brand']);
    Route::delete('/brand', [MastersController::class, 'delete_brand']);

    // Route::post('/add_pdf', [MastersController::class, 'add_pdf_template']);
    // Route::get('/pdf', [MastersController::class, 'pdf_template']);
    // Route::post('/update_pdf/{id?}', [MastersController::class, 'edit_pdf_template']);
    // Route::delete('/pdf', [MastersController::class, 'delete_pdf_template']);

    // Route::post('/add_pdf', [MastersController::class, 'add_pdf_template']);
    // Route::get('/pdf', [MastersController::class, 'pdf_template']);
    // Route::post('/update_pdf/{id?}', [MastersController::class, 'edit_pdf_template']);
    // Route::delete('/pdf', [MastersController::class, 'delete_pdf_template']);



    Route::post('/logout', [AuthController::class, 'logout']);
});
