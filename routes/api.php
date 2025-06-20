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
use App\Http\Controllers\ResetController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\QuotationTermMasterController;
use App\Http\Controllers\PurchaseBackController;
use App\Http\Controllers\HelperController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\VendorsController;
use App\Http\Controllers\CustomerVisitController;
use App\Http\Controllers\StatsController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/register', [UsersController::class, 'register']);

Route::post('/login/{otp?}', [AuthController::class, 'login']);

Route::post('/get_otp', [AuthController::class, 'generate_otp']);

// Route::get('/client_migrate', [ClientsController::class, 'importClientsData']);

Route::get('/stats', [StatsController::class, 'index']);
Route::get('/import-adjustment', [StatsController::class, 'importAdjustment']);
Route::get('/import-fabrication', [StatsController::class, 'importFabrication']);

Route::middleware(['auth:sanctum'])->group(function () {

    // Route::get('/reset_calculation/{id}', [ResetController::class, 'stock_calculation']);
    Route::get('/reset_calculation', [ResetController::class, 'stock_calculation']);
    Route::get('/reset_status', [ResetController::class, 'reset_queue_status']);


    Route::get('/user', [UsersController::class, 'view']);
    Route::post('/fetch', [UsersController::class, 'view_user']);
    Route::post('/edit/{id}', [UsersController::class, 'update']);
    Route::delete('/delete/{id}', [UsersController::class, 'delete']);
    
    Route::get('/users_migrate', [UsersController::class, 'get_migrate']);

    Route::post('/client', [ClientsController::class, 'add_clients']);
    // Route::get('/client', [ClientsController::class, 'view_clients']);
    Route::post('/get_client/{id?}', [ClientsController::class, 'view_clients']);
    Route::post('/update_client/{id}', [ClientsController::class, 'update_clients']);
    Route::delete('/client/{id}', [ClientsController::class, 'delete_clients']);

    Route::get('/client_migrate', [ClientsController::class, 'importClientsData']);

    Route::post('/export_clients', [ClientsController::class, 'export_clients']);
    Route::post('/update_client_address/{id}', [ClientsController::class, 'update_client_address']);
    Route::post('/update_client_gst/{id}', [ClientsController::class, 'update_client_gst']);

    Route::post('/suppliers', [SuppliersController::class, 'add_suppliers']);
    Route::post('/get_suppliers/{id?}', [SuppliersController::class, 'view_suppliers']);
    Route::post('/update_suppliers/{id}', [SuppliersController::class, 'update_suppliers']);
    Route::delete('/suppliers/{id}', [SuppliersController::class, 'delete_supplier']);

    Route::get('/suppliers_migrate', [SuppliersController::class, 'importSuppliersData']);
    Route::post('/export_suppliers', [SuppliersController::class, 'export_suppliers']);
    Route::post('/update_supplier_address/{id}', [SuppliersController::class, 'update_supplier_address']);
    Route::post('/update_supplier_gst/{id}', [SuppliersController::class, 'update_supplier_gst']);

    Route::post('/products', [MastersController::class, 'add_products']);
    Route::post('/get_products/{id?}', [MastersController::class, 'view_products']);
    Route::post('/update_products/{id}', [MastersController::class, 'edit_products']);
    Route::delete('/products/{id}', [MastersController::class, 'delete_products']);

    Route::get('/get_products', [MastersController::class, 'get_product']);

    Route::get('products_migrate', [MastersController::class, 'importProducts']);
    Route::get('opening_stock_migrate', [MastersController::class, 'importOpeningStock']);
    Route::get('financial_year_migrate', [MastersController::class, 'importFinancialYears']);

    Route::post('export_product', [MastersController::class, 'export_products']);
    
    Route::get('/get_tax', [MastersController::class, 'get_tax']);
    Route::get('/get_unit', [MastersController::class, 'get_unit']);

    Route::post('/year', [MastersController::class, 'add_f_year']);
    Route::get('/year', [MastersController::class, 'view_f_year']);
    Route::post('/update_year/{id}', [MastersController::class, 'edit_f_year']);
    Route::delete('/year/{id}', [MastersController::class, 'delete_f_year']);

    Route::post('/godown', [MastersController::class, 'add_godown']);
    Route::get('/godown', [MastersController::class, 'view_godown']);
    Route::post('/update_godown/{id?}', [MastersController::class, 'edit_godown']);
    Route::delete('/godown/{id?}', [MastersController::class, 'delete_godown']);

    Route::post('/category', [MastersController::class, 'add_category']);
    Route::get('/category', [MastersController::class, 'view_category']);
    Route::post('/update_category/{id?}', [MastersController::class, 'edit_category']);
    Route::delete('/category/{id?}', [MastersController::class, 'delete_category']);

    Route::post('/sub_category', [MastersController::class, 'add_sub_category']);
    Route::get('/sub_category', [MastersController::class, 'view_sub_category']);
    Route::post('/update_sub_category/{id?}', [MastersController::class, 'edit_sub_category']);
    Route::delete('/sub_category/{id?}', [MastersController::class, 'delete_sub_category']);

    Route::post('/group', [MastersController::class, 'add_group']);
    Route::get('/group', [MastersController::class, 'view_group']);
    Route::post('/update_group/{id?}', [MastersController::class, 'edit_group']);
    Route::delete('/group/{id?}', [MastersController::class, 'delete_group']);

    Route::post('/add_pdf_template', [MastersController::class, 'add_pdf_template']);

    Route::get('/get_pdf_template', [MastersController::class, 'pdf_template']);

    Route::post('/update_pdf_template/{id}', [MastersController::class, 'edit_pdf_template']);

    Route::delete('/get_pdf_template/{id}', [MastersController::class, 'delete_pdf_template']);

    Route::get('/pdf_template_migrate', [MastersController::class, 'importPdfTemplates']);

    Route::prefix('customer_visit')->group(function () {
        Route::post('/store', [CustomerVisitController::class, 'register_customer_visit']); // Create Products
        Route::post('/index', [CustomerVisitController::class, 'fetch']); // Retrieve products
        Route::post('/update/{id}', [CustomerVisitController::class, 'edit']); // Update a specific user
        Route::post('/delete_specific_uploads/{id}', [CustomerVisitController::class, 'deleteUploads']); // Update a specific user
        Route::delete('/{id}', [CustomerVisitController::class, 'delete']); // Update a specific user
        Route::get('/migrate', [CustomerVisitController::class, 'importCustomerVisits']); //Migrate from `old customer visit` table
    });

    Route::post('/add_quotations', [QuotationsController::class, 'add_quotations']);
    Route::post('/quotations/{id?}', [QuotationsController::class, 'view_quotations']);
    Route::post('/update_quotations/{id?}', [QuotationsController::class, 'update_quotations']);
    Route::delete('/quotations/{id?}', [QuotationsController::class, 'delete_quotations']);
    Route::get('/quotations_migrate', [QuotationsController::class, 'importQuotations']);
    Route::post('/update_quotation_status/{id}', [QuotationsController::class, 'updateQuotationStatus']);
    Route::get('/quotation_generate_pdf/{id}', [QuotationsController::class, 'generateQuotationPDF']);

    Route::post('/quotation_by_product', [QuotationsController::class, 'fetchQuotationsAllProducts']);

    Route::post('/add_sales_order', [SalesOrderController::class, 'add_sales_order']);
    Route::post('/sales_order/{id?}', [SalesOrderController::class, 'view_sales_order']);
    Route::post('/update_sales_order/{id?}', [SalesOrderController::class, 'edit_sales_order']);
    Route::delete('/sales_order/{id?}', [SalesOrderController::class, 'delete_sales_order']);
    Route::post('/pending_ref_no', [SalesOrderController::class, 'getPendingSupplierseOrders']);
    Route::post('/pending_partial_no', [SalesOrderController::class, 'getPendingPartialSalesOrders']);

    Route::get('/sales_order_migrate', [SalesOrderController::class, 'importSalesOrders']);

    Route::post('/export_sales_orders', [SalesOrderController::class, 'export_sales_orders']);

    Route::post('/export_sales_order_report', [SalesOrderController::class, 'exportSalesOrderReport']);

    Route::post('/sales_order_by_product/{id}', [SalesOrderController::class, 'fetchSalesOrdersByProduct']);

    Route::post('/sales_order_by_product', [SalesOrderController::class, 'fetchSalesOrdersAllProduct']);

    Route::post('/add_sales_invoice', [SalesInvoiceController::class, 'add_sales_invoice']);
    Route::post('/sales_invoice/{id?}', [SalesInvoiceController::class, 'view_sales_invoice']);
    Route::post('/update_sales_invoice/{id?}', [SalesInvoiceController::class, 'edit_sales_invoice']);
    Route::delete('/sales_invoice/{id?}', [SalesInvoiceController::class, 'delete_sales_invoice']);

    Route::get('/sales_invoice_migrate', [SalesInvoiceController::class, 'importSalesInvoices']);

    Route::post('/export_sales_invoice_report', [SalesInvoiceController::class, 'exportSalesInvoiceReport']);

    Route::post('/sales_by_product/{id}', [SalesInvoiceController::class, 'fetchSalesByProduct']);

    Route::post('/sales_by_product', [SalesInvoiceController::class, 'fetchSalesAllProducts']);

    Route::post('/product_wise', [SalesInvoiceController::class, 'product_wise_profit']);
    Route::post('/client_wise', [SalesInvoiceController::class, 'client_wise_profit']);

    Route::post('/export_product_wise', [SalesInvoiceController::class, 'exportProductWiseProfitExcel']);
    Route::post('/export_client_wise', [SalesInvoiceController::class, 'exportClientWiseProfitExcel']);

    Route::post('/cash_invoice', [SalesInvoiceController::class, 'getCashSalesInvoices']);
    Route::post('/commission_invoice', [SalesInvoiceController::class, 'getCommissionSalesInvoices']);

    Route::post('/export_cash_invoice', [SalesInvoiceController::class, 'exportCashInvoices']);
    Route::post('/export_commission_invoice', [SalesInvoiceController::class, 'exportCommissionInvoices']);

    Route::post('/add_sales_return', [SalesReturnController::class, 'add_sales_return']);
    Route::post('/sales_return/{id?}', [SalesReturnController::class, 'view_sales_return']);
    Route::post('/update_sales_return/{id?}', [SalesReturnController::class, 'edit_sales_return']);
    Route::delete('/sales_return/{id?}', [SalesReturnController::class, 'delete_sales_return']);

    Route::get('/sales_return_migrate', [SalesReturnController::class, 'importSalesReturns']);

    Route::post('/add_credit_note', [CreditNoteController::class, 'add_credit_note']);
    Route::post('/credit_note/{id?}', [CreditNoteController::class, 'view_credit_note']);
    Route::post('/update_credit_note/{id?}', [CreditNoteController::class, 'edit_credit_note']);
    Route::delete('/credit_note/{id?}', [CreditNoteController::class, 'delete_credit_note']);
    Route::get('/credit-note-type', [CreditNoteController::class, 'getTypeList']);

    Route::get('/credit_note_migrate', [CreditNoteController::class, 'importCreditNotes']);

    Route::post('/add_test_certificate', [TestCertificateController::class, 'add_test_certificate']);
    Route::post('/test_certificate', [TestCertificateController::class, 'view_test_certificate']);
    Route::post('/update_test_certificate/{id?}', [TestCertificateController::class, 'edit_test_certificate']);
    Route::delete('/test_certificate/{id?}', [TestCertificateController::class, 'delete_test_certificate']);

    Route::get('/test_certificate_migrate', [TestCertificateController::class, 'importTestCertificates']);

    Route::post('/add_purchase_order', [PurchaseOrderController::class, 'add_purchase_order']);
    Route::post('/purchase_order/{id?}', [PurchaseOrderController::class, 'view_purchase_order']);
    Route::post('/update_purchase_order/{id?}', [PurchaseOrderController::class, 'edit_purchase_order']);
    Route::delete('/purchase_order/{id?}', [PurchaseOrderController::class, 'delete_purchase_order']);
    Route::get('/purchaseorder_migrate', [PurchaseOrderController::class, 'importPurchaseOrders']);
    Route::post('/pending_oa_no', [PurchaseOrderController::class, 'getPendingPurchaseOrders']);

    Route::post('/export_purchase_order_report', [PurchaseOrderController::class, 'exportPurchaseOrdersReport']);

    Route::post('/purchase_order_by_product/{id}', [PurchaseOrderController::class, 'fetchPurchaseOrdersByProduct']);

    Route::post('/purchase_order_by_product', [PurchaseOrderController::class, 'fetchPurchaseOrdersAllProduct']);

    Route::post('/add_purchase_invoice', [PurchaseInvoiceController::class, 'add_purchase_invoice']);
    Route::post('/purchase_invoice/{id?}', [PurchaseInvoiceController::class, 'view_purchase_invoice']);
    Route::post('/update_purchase_invoice/{id?}', [PurchaseInvoiceController::class, 'edit_purchase_invoice']);
    Route::delete('/purchase_invoice/{id?}', [PurchaseInvoiceController::class, 'delete_purchase_invoice']);

    Route::get('/purchase_invoice_migrate', [PurchaseInvoiceController::class, 'importPurchaseInvoices']);

    Route::post('/export_purchase_invoice_report', [PurchaseInvoiceController::class, 'exportPurchaseInvoiceReport']);

    Route::post('/purchase_by_product/{id}', [PurchaseInvoiceController::class, 'fetchPurchasesByProduct']);

    Route::post('/purchase_by_product', [PurchaseInvoiceController::class, 'fetchPurchasesAllProduct']);

    Route::post('/add_purchase_return', [PurchaseReturnController::class, 'add_purchase_return']);
    Route::post('/purchase_return/{id?}', [PurchaseReturnController::class, 'view_purchase_return']);
    Route::post('/update_purchase_return/{id?}', [PurchaseReturnController::class, 'edit_purchase_return']);
    Route::delete('/purchase_return/{id?}', [PurchaseReturnController::class, 'delete_purchase_return']);
    Route::get('/purchase_return_migrate', [PurchaseReturnController::class, 'importPurchaseReturns']);

    Route::post('/add_debit_note', [DebitNoteController::class, 'add_debit_note']);
    Route::post('/debit_note/{id?}', [DebitNoteController::class, 'view_debit_note']);
    Route::post('/update_debit_note/{id?}', [DebitNoteController::class, 'edit_debit_note']);
    Route::delete('/debit_note/{id?}', [DebitNoteController::class, 'delete_debit_note']);

    Route::get('/debit_note_migrate', [DebitNoteController::class, 'importDebitNotes']);

    Route::post('/add_stock_transfer', [StockTransferController::class, 'add_stock_transfer']);
    Route::post('/stock_transfer/{id?}', [StockTransferController::class, 'view_stock_transfer']);
    Route::post('/update_stock_transfer/{id?}', [StockTransferController::class, 'edit_stock_transfer']);
    Route::delete('/stock_transfer/{id?}', [StockTransferController::class, 'delete_stock_transfer']);

    Route::get('/stock_transfer_migrate', [StockTransferController::class, 'importStockTransfers']);

    Route::post('/stock_transfers_by_product/{productId}', [StockTransferController::class, 'fetchStockTransfersByProduct']);

    Route::post('/stock_transfers_by_product', [StockTransferController::class, 'fetchStockTransfersAllProduct']);

    Route::post('/add_assembly', [AssemblyController::class, 'add_assembly']);
    Route::post('/assembly/{id?}', [AssemblyController::class, 'view_assembly']);
    Route::post('/product_assembly/{id?}', [AssemblyController::class, 'view_product_assembly']);
    Route::post('/update_assembly/{id}', [AssemblyController::class, 'edit_assembly']);
    Route::delete('/assembly/{id?}', [AssemblyController::class, 'delete_assembly']);

    Route::get('/assembly_migrate', [AssemblyController::class, 'importAssemblies']);

    Route::post('/add_assembly_operations', [AssemblyOperationsController::class, 'add_assembly_operations']);
    Route::post('/assembly_operations/{id?}', [AssemblyOperationsController::class, 'assembly_operations']);
    Route::post('/update_assembly_operations/{id}', [AssemblyOperationsController::class, 'edit_assembly_operations']);
    Route::delete('/assembly_operations/{id?}', [AssemblyOperationsController::class, 'delete_assembly_operations']);

    Route::get('/assembly_operations_migrate', [AssemblyOperationsController::class, 'importAssemblyOperations']);

    Route::post('/assembly_operations_by_product/{productId}', [AssemblyOperationsController::class, 'fetchAssemblyByProduct']);

    Route::prefix('fabrication')->group(function () {
        Route::post('/add', [FabricationController::class, 'add']);
        Route::post('/fetch', [FabricationController::class, 'view']);
        Route::post('/edit/{id?}', [FabricationController::class, 'edit']);
        Route::delete('/delete/{id?}', [FabricationController::class, 'delete']);
    });

    Route::prefix('counter')->group(function () {
        Route::post('/add', [CounterController::class, 'add']);
        Route::post('/fetch/{id?}', [CounterController::class, 'view']);
        Route::post('/edit/{id?}', [CounterController::class, 'edit']);
        Route::delete('/delete/{id?}', [CounterController::class, 'delete']);
    });

    Route::post('/opening_stock', [MastersController::class, 'add_opening_stock']);
    Route::get('/opening_stock', [MastersController::class, 'view_opening_stock']);

    Route::post('/closing_stock', [MastersController::class, 'add_closing_stock']);
    Route::get('/closing_stock', [MastersController::class, 'view_closing_stock']);


    Route::post('/reset_product', [ResetController::class, 'reset_product']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/client_type', [MastersController::class, 'getClientsTypes']);

    Route::get('/client_category', [MastersController::class, 'getClientsCategories']);

    Route::post('/add_country', [CountryController::class, 'registerCountries']);
    Route::get('/country', [CountryController::class, 'viewCountries']);
    Route::post('/edit_country/{id}', [CountryController::class, 'updateCountry']);
    Route::delete('/country/{id}', [CountryController::class, 'deleteCountry']);

    Route::post('/add_state', [StateController::class, 'registerStates']);
    Route::get('/state', [StateController::class, 'viewStates']);
    Route::post('/edit_state/{id}', [StateController::class, 'updateState']);
    Route::delete('/state/{id}', [StateController::class, 'deleteState']);

    Route::post('/quotation-terms', [QuotationTermMasterController::class, 'add']);
    Route::get('/quotation-terms', [QuotationTermMasterController::class, 'retrieve']);
    Route::post('/edit-quotation-terms/{id}', [QuotationTermMasterController::class, 'update']);
    Route::delete('/quotation-terms/{id}', [QuotationTermMasterController::class, 'delete']);

    Route::post('/channel', [ChannelController::class, 'add']); // Create Channel
    Route::get('/channels', [ChannelController::class, 'retrieve']); // View All Channels
    Route::post('/update_channel/{id}', [ChannelController::class, 'update']); // Update Channel
    Route::delete('/channel/{id}', [ChannelController::class, 'destroy']); // Delete Channel

    Route::post('/purchase_back', [PurchaseBackController::class, 'add_purchase_back']); // Create purchase-bcak
    Route::get('/purchase_back', [PurchaseBackController::class, 'fetch_purchase_back']); // View All purchase-back

    Route::prefix('report')->group(function () {
        Route::post('/dashboard', [HelperController::class, 'dashboard']);

        Route::get('/statistic', [HelperController::class, 'getSummary']);

        Route::post('/fy_wise_totals', [HelperController::class, 'fyWisePurchaseTotals']);

        Route::post('/monthly_summary', [HelperController::class, 'getMonthlyPurchaseSalesSummary']);

        Route::post('/sales_barchart', [HelperController::class, 'getMonthlySalesSummary']);

        Route::post('/sales_graph', [HelperController::class, 'getMonthlyCumulativeSalesSummary']);

        Route::post('/profit_distribution', [HelperController::class, 'getDailyProfitDistribution']);

        Route::post('/quotaion_statistic', [HelperController::class, 'getMonthlyQuotationStatusReport']);

        Route::post('/product_quotation', [HelperController::class, 'getProductWiseQuotations']);

        Route::post('/export_product_quotation', [HelperController::class, 'exportProductWiseQuotations']);

        Route::post('/client_quotation', [HelperController::class, 'getClientWiseQuotations']);

        Route::post('/export_client_quotation', [HelperController::class, 'exportClientWiseQuotations']);

        Route::post('/product_profit', [HelperController::class, 'getProductWiseSalesSummary']);

        Route::post('/client_profit', [HelperController::class, 'getClientWiseSalesSummary']);

        Route::get('/product_profit_fy', [HelperController::class, 'getProductWiseYearlySalesSummary']);

        Route::get('/client_profit_fy', [HelperController::class, 'getClientWiseYearlySalesSummary']);

        Route::get('/get_types', [HelperController::class, 'types']);

        Route::post('/monthly_billing_summary', [HelperController::class, 'getMonthlyBillingSummary']);

        Route::post('/export_monthly_billing_summary', [HelperController::class, 'exportMonthlyBillingSummary']);

        Route::post('/client_wise_summary', [HelperController::class, 'getClientYearlySalesSummary']);

        Route::post('/export_client_wise_summary', [HelperController::class, 'exportClientWiseSummary']);

        Route::post('/get_product_timeline/{productId}', [HelperController::class, 'product_timeline']);
    });

    Route::prefix('adjustment')->group(function () {
        Route::post('/add', [AdjustmentController::class, 'store']);
         Route::post('/retrieve/{id?}', [AdjustmentController::class, 'fetch']);
        Route::post('/edit/{id}', [AdjustmentController::class, 'update']);
        Route::delete('/delete/{id}', [AdjustmentController::class, 'delete']);
    });

    Route::prefix('vendor')->group(function () {
        Route::post('/add', [VendorsController::class, 'create']);
        Route::post('/retrieve/{id?}', [VendorsController::class, 'fetch']);
        Route::post('/edit/{id}', [VendorsController::class, 'update']);
        Route::delete('/delete/{id}', [VendorsController::class, 'delete']);
    });

    Route::post('/add_lot', [LotController::class, 'add']); // Create Channel
    Route::post('/fetch_lot/{id?}', [LotController::class, 'retrieve']); // View All Channels
    Route::post('/update_lot/{id}', [LotController::class, 'update']); // Update Channel
    Route::delete('/lot/{id}', [LotController::class, 'destroy']); // Delete Channel
    Route::get('/lot_info_migrate', [LotController::class, 'importLotInfo']);
});
