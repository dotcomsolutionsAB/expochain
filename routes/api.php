<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\MastersController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\SuppliersController;
use App\Http\Controllers\QuotationsController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\TestCertificateController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\DebitNoteController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\AssemblyController;
use App\Http\Controllers\AssemblyOperationsController;
use App\Http\Controllers\FabricationController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/register', [UsersController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user', [UsersController::class, 'view']);
    Route::post('/edit/{id}', [UsersController::class, 'update']);
    Route::delete('/delete/{id}', [UsersController::class, 'delete']);

    Route::post('/client', [ClientsController::class, 'add_clients']);
    Route::get('/client', [ClientsController::class, 'view_clients']);
    Route::post('/update_client/{id}', [ClientsController::class, 'update_clients']);
    Route::delete('/client/{id}', [ClientsController::class, 'delete_clients']);

    Route::post('/suppliers', [SuppliersController::class, 'add_suppliers']);
    Route::get('/suppliers', [SuppliersController::class, 'view_suppliers']);
    Route::post('/update_suppliers/{id}', [SuppliersController::class, 'update_suppliers']);
    Route::delete('/suppliers/{id}', [SuppliersController::class, 'delete_supplier']);

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
    Route::delete('/godown/{id?}', [MastersController::class, 'delete_godown']);

    Route::post('/category', [MastersController::class, 'add_category']);
    Route::get('/pdf', [MastersController::class, 'view_category']);
    Route::post('/update_category/{id?}', [MastersController::class, 'edit_category']);
    Route::delete('/pdf/{id?}', [MastersController::class, 'delete_category']);

    Route::post('/sub_category', [MastersController::class, 'add_sub_category']);
    Route::get('/pdf', [MastersController::class, 'sub_category']);
    Route::post('/update_pdf/{id?}', [MastersController::class, 'edit_sub_category']);
    Route::delete('/pdf/{id?}', [MastersController::class, 'delete_sub_category']);

    Route::post('/brand', [MastersController::class, 'add_brand']);
    Route::get('/brand', [MastersController::class, 'brand']);
    Route::post('/update_brand/{id?}', [MastersController::class, 'edit_brand']);
    Route::delete('/brand/{id?}', [MastersController::class, 'delete_brand']);

    Route::post('/add_quotations', [QuotationsController::class, 'add_quotations']);
    Route::get('/quotations', [QuotationsController::class, 'quotations']);
    Route::post('/update_quotations/{id?}', [MastersController::class, 'edit_quotations']);
    Route::delete('/quotations/{id?}', [MastersController::class, 'delete_quotations']);

    Route::post('/add_sales', [SalesOrderController::class, 'add_sales_order']);
    Route::get('/sales', [SalesOrderController::class, 'pdf_template']);
    Route::post('/update_sales/{id?}', [SalesOrderController::class, 'edit_pdf_template']);
    Route::delete('/sales/{id?}', [SalesOrderController::class, 'delete_pdf_template']);

    Route::post('/add_sales_invoice', [SalesInvoiceController::class, 'add_sales_invoice']);
    Route::get('/sales', [SalesOrderController::class, 'pdf_template']);
    Route::post('/update_sales/{id?}', [SalesOrderController::class, 'edit_pdf_template']);
    Route::delete('/sales/{id?}', [SalesOrderController::class, 'delete_pdf_template']);

    Route::post('/add_credit_note', [CreditNoteController::class, 'add_credit_note']);
    Route::get('/credit_note', [CreditNoteController::class, 'pdf_template']);
    Route::post('/update_credit_note/{id?}', [CreditNoteController::class, 'edit_credit_note']);
    Route::delete('/credit_note/{id?}', [CreditNoteController::class, 'delete_credit_note']);

    Route::post('/add_test_certificate', [TestCertificateController::class, 'add_test_certificate']);
    Route::get('/test_certificate', [TestCertificateController::class, 'test_certificate']);
    Route::post('/update_test_certificate/{id?}', [TestCertificateController::class, 'edit_test_certificate']);
    Route::delete('/test_certificate/{id?}', [TestCertificateController::class, 'delete_test_certificate']);

    Route::post('/add_purchase_invoice', [PurchaseInvoiceController::class, 'add_purchase_invoice']);
    Route::get('/purchase_invoice', [PurchaseInvoiceController::class, 'purchase_invoice']);
    Route::post('/update_purchase_invoice/{id?}', [PurchaseInvoiceController::class, 'edit_purchase_invoice']);
    Route::delete('/purchase_invoice/{id?}', [PurchaseInvoiceController::class, 'delete_purchase_invoice']);

    Route::post('/add_purchase_order', [PurchaseOrderController::class, 'add_purchase_order']);
    Route::get('/purchase_order', [PurchaseOrderController::class, 'purchase_order']);
    Route::post('/update_purchase_order/{id?}', [PurchaseOrderController::class, 'edit_purchase_order']);
    Route::delete('/purchase_order/{id?}', [PurchaseOrderController::class, 'delete_purchase_order']);

    Route::post('/add_purchase_return', [PurchaseReturnController::class, 'add_purchase_return']);
    Route::get('/purchase_return', [PurchaseReturnController::class, 'purchase_return']);
    Route::post('/update_purchase_return/{id?}', [PurchaseReturnController::class, 'edit_purchase_return']);
    Route::delete('/purchase_return/{id?}', [PurchaseReturnController::class, 'delete_purchase_return']);

    Route::post('/add_debit_note', [DebitNoteController::class, 'add_debit_note']);
    Route::get('/debit_note', [DebitNoteController::class, 'debit_note']);
    Route::post('/update_debit_note/{id?}', [DebitNoteController::class, 'edit_debit_note']);
    Route::delete('/debit_note/{id?}', [DebitNoteController::class, 'delete_debit_note']);

    Route::post('/add_stock_transfer', [StockTransferController::class, 'add_stock_transfer']);
    Route::get('/tock_transfer', [StockTransferController::class, 'stock_transfer']);
    Route::post('/update_stock_transfer/{id?}', [StockTransferController::class, 'edit_tock_transfer']);
    Route::delete('/stock_transfer/{id?}', [StockTransferController::class, 'delete_tock_transfer']);

    Route::post('/add_assembly', [AssemblyController::class, 'add_assembly']);
    Route::get('/assembly', [AssemblyController::class, 'assembly']);
    Route::post('/update_assembly/{id?}', [AssemblyController::class, 'edit_assembly']);
    Route::delete('/assembly/{id?}', [AssemblyController::class, 'delete_assembly']);

    Route::post('/add_assembly_operations', [AssemblyOperationsController::class, 'add_assembly_operations']);
    Route::get('/assembly_operations', [AssemblyOperationsController::class, 'assembly_operations']);
    Route::post('/update_assembly_operations/{id?}', [AssemblyOperationsController::class, 'edit_assembly_operations']);
    Route::delete('/assembly_operations/{id?}', [AssemblyOperationsController::class, 'delete_assembly_operations']);

    Route::post('/add_fabrication', [FabricationController::class, 'add_fabrication']);
    Route::get('/fabrication', [FabricationController::class, 'fabrication']);
    Route::post('/update_fabrication/{id?}', [FabricationController::class, 'edit_fabrication']);
    Route::delete('/fabrication/{id?}', [FabricationController::class, 'delete_fabrication']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
