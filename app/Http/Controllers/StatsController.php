<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http; // For HTTP client
use Illuminate\Http\Request;

use App\Models\AdjustmentModel;
use App\Models\AssemblyModel;
use App\Models\AssemblyOperationModel;
use App\Models\AssemblyOperationProductsModel;
use App\Models\AssemblyProductsModel;
use App\Models\ClientsModel;
use App\Models\CategoryModel;
use App\Models\ChannelModel;
use App\Models\ClientAddressModel;
use App\Models\ClientContactsModel;
use App\Models\ClosingStockModel;
use App\Models\CompanyModel;
use App\Models\CounterModel;
use App\Models\CountryModel;
use App\Models\CreditNoteModel;
use App\Models\CreditNoteProductsModel;
use App\Models\CustomerVisitModel;
use App\Models\DebitNoteModel;
use App\Models\DebitNoteProductsModel;
use App\Models\DiscountModel;
use App\Models\EmailQueueModel;
use App\Models\FabricationModel;
use App\Models\FabricationProductsModel;
use App\Models\FinancialYearModel;
use App\Models\GodownModel;
use App\Models\GroupModel;
use App\Models\LotModel;
use App\Models\OpeningStockModel;
use App\Models\PdfTemplateModel;
use App\Models\ProductsModel;
use App\Models\PurchaseBackModel;
use App\Models\PurchaseInvoiceAddonsModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\PurchaseOrderAddonsModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderProductsModel;
use App\Models\PurchaseOrderTermsModel;
use App\Models\PurchaseReturnModel;
use App\Models\PurchaseReturnProductsModel;
use App\Models\QuotationAddonsModel;
use App\Models\QuotationProductsModel;
use App\Models\QuotationsModel;
use App\Models\QuotationTermMasterModel;
use App\Models\QuotationTermsModel;
use App\Models\ResetQueueModel;
use App\Models\SalesInvoiceAddonsModel;
use App\Models\SalesInvoiceModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\SalesOrderAddonsModel;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderProductsModel;
use App\Models\SalesReturnModel;
use App\Models\SalesReturnProductsModel;
use App\Models\StateModel;
use App\Models\StockTransferModel;
use App\Models\StockTransferProductsModel;
use App\Models\SubCategoryModel;
use App\Models\SupplierAddressModel;
use App\Models\SuppliersContactsModel;
use App\Models\SuppliersModel;
use App\Models\TestCertificateModel;
use App\Models\TestCertificateProductsModel;
use App\Models\UploadsModel;
use App\Models\User;
use App\Models\VendorsModel;
use App\Models\WhatsappQueueModel;

class StatsController extends Controller
{
    public function index()
    {
        // Collect counts for all models here
        $counts = [

            // Initial 6 models
            'Adjustments' => AdjustmentModel::count(),
            'Assembly' => AssemblyModel::count(),
            'Assembly Operation' => AssemblyOperationModel::count(),
            'Assembly Operation Products' => AssemblyOperationProductsModel::count(),
            'Assembly Products' => AssemblyProductsModel::count(),
            'Clients' => ClientsModel::count(),
            // Additional models
            'Category' => CategoryModel::count(),
            'Channel' => ChannelModel::count(),
            'Client Address' => ClientAddressModel::count(),
            'Client Contacts' => ClientContactsModel::count(),
            'Closing Stock' => ClosingStockModel::count(),
            'Company' => CompanyModel::count(),
            'Counter' => CounterModel::count(),
            'Country' => CountryModel::count(),
            'Credit Note' => CreditNoteModel::count(),
            'Credit Note Products' => CreditNoteProductsModel::count(),
            'Customer Visit' => CustomerVisitModel::count(),
            'Debit Note' => DebitNoteModel::count(),
            'Debit Note Products' => DebitNoteProductsModel::count(),
            // New models
            'Discount' => DiscountModel::count(),
            'Email Queue' => EmailQueueModel::count(),
            'Fabrication' => FabricationModel::count(),
            'Fabrication Products' => FabricationProductsModel::count(),
            'Financial Year' => FinancialYearModel::count(),
            'Godown' => GodownModel::count(),
            'Group' => GroupModel::count(),
            'Lot' => LotModel::count(),
            'Opening Stock' => OpeningStockModel::count(),
            'PDF Template' => PdfTemplateModel::count(),
            'products' => ProductsModel::count(),
            'Purchase Back' => PurchaseBackModel::count(),
            'Purchase Invoice Addons' => PurchaseInvoiceAddonsModel::count(),
            'Purchase Invoice' => PurchaseInvoiceModel::count(),
            'Purchase Invoice Products' => PurchaseInvoiceProductsModel::count(),
            'Purchase Order Addons' => PurchaseOrderAddonsModel::count(),
            'Purchase Order' => PurchaseOrderModel::count(),
            'Purchase Order Products' => PurchaseOrderProductsModel::count(),
            'Purchase Order Terms' => PurchaseOrderTermsModel::count(),
            'Purchase Return' => PurchaseReturnModel::count(),
            'Purchase Return Products' => PurchaseReturnProductsModel::count(),
            'Quotation Addons' => QuotationAddonsModel::count(),
            'Quotation Products' => QuotationProductsModel::count(),
            'Quotation' => QuotationsModel::count(),
            'Quotation Term Master' => QuotationTermMasterModel::count(),
            'Quotation Terms' => QuotationTermsModel::count(),
            'Reset Queue' => ResetQueueModel::count(),
            'Sales Invoice Addons' => SalesInvoiceAddonsModel::count(),
            'Sales Invoice' => SalesInvoiceModel::count(),
            'Sales Invoice Products' => SalesInvoiceProductsModel::count(),
            'Sales Order Addons' => SalesOrderAddonsModel::count(),
            'Sales Order' => SalesOrderModel::count(),
            'Sales Order Products' => SalesOrderProductsModel::count(),
            'Sales Return' => SalesReturnModel::count(),
            'Sales Return Products' => SalesReturnProductsModel::count(),
            'State' => StateModel::count(),
            'Stock Transfer' => StockTransferModel::count(),
            'Stock Transfer Products' => StockTransferProductsModel::count(),
            // ----------------------------------//
            'Sub Category' => SubCategoryModel::count(),
            'Supplier Address' => SupplierAddressModel::count(),
            'Suppliers Contacts' => SuppliersContactsModel::count(),
            'Suppliers' => SuppliersModel::count(),
            'Test Certificate' => TestCertificateModel::count(),
            'Test Certificate Products' => TestCertificateProductsModel::count(),
            'Uploads' => UploadsModel::count(),
            'User' => User::count(),
            'Vendors' => VendorsModel::count(),
            'Whatsapp Queue' => WhatsappQueueModel::count(),


        ];

        // Build HTML directly
        $html = '
                <!DOCTYPE html>
                <html lang="en">
                    <head>
                        <meta charset="UTF-8" />
                        <meta name="viewport" content="width=device-width, initial-scale=1" />
                        <title>Stats Overview</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 40px auto; max-width: 700px; background: #f9f9f9; }
                            table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                            th, td { padding: 12px 20px; border: 1px solid #ddd; text-align: left; }
                            th { background-color: #007bff; color: white; }
                            caption { font-size: 1.5em; margin-bottom: 10px; font-weight: bold; color: #333; }
                            tbody tr:hover { background-color: #f1f7ff; }
                        </style>
                    </head>
                    <body>
                        <table>
                            <caption>Database Table Counts</caption>
                            <thead>
                                <tr>
                                    <th>Model Name</th>
                                    <th>Total Records</th>
                                </tr>
                            </thead>
                            <tbody>';

                                foreach ($counts as $model => $count) {
                                    $html .= '<tr><td>' . htmlspecialchars($model) . '</td><td>' . htmlspecialchars($count) . '</td></tr>';
                                }

                                $html .= '
                            </tbody>
                        </table>
                    </body>
                </html>
            ';

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    // public function importAdjustment()
    // {
    //     // Fetch API data
    //     $response = Http::get('https://expo.egsm.in/assets/custom/migrate/adjustment.php');

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch API data'], 500);
    //     }

    //     $data = $response->json();

    //     if (!isset($data['data']) || !is_array($data['data'])) {
    //         return response()->json(['error' => 'Invalid API response format'], 422);
    //     }

    //     $inserted = 0;
    //     $skipped = 0;

    //     foreach ($data['data'] as $item) {
    //         // Basic validation
    //         if (empty($item['date']) || empty($item['product']) || !isset($item['quantity']) || !isset($item['type'])) {
    //             $skipped++;
    //             continue;
    //         }

    //         // Find product_id by product name (case insensitive)
    //         $product = ProductsModel::where('name', 'LIKE', trim($item['product']))->first();
    //         if (!$product) {
    //             // Skip or handle missing product
    //             $skipped++;
    //             continue;
    //         }

    //         // Find godown_id by place (case insensitive)
    //         $godown = GodownModel::where('name', 'LIKE', trim($item['place']))->first();
    //         if (!$godown) {
    //             // Optional: skip or set null or default godown
    //             $skipped++;
    //             continue;
    //         }

    //         // Prepare data for insertion
    //         $adjustmentData = [
    //             'company_id' => 1, // Change this as needed
    //             'adjustment_date' => $item['date'],
    //             'product_id' => $product->id,
    //             'quantity' => (float) $item['quantity'],
    //             'godown_id' => $godown->id,
    //             'type' => $item['type'],
    //         ];

    //         // Insert record
    //         AdjustmentModel::create($adjustmentData);
    //         $inserted++;
    //     }

    //     return response()->json([
    //         'message' => "Import completed",
    //         'inserted' => $inserted,
    //         'skipped' => $skipped,
    //         'total' => count($data['data']),
    //     ]);
    // }

public function importAdjustment()
{
    $response = Http::get('https://expo.egsm.in/assets/custom/migrate/adjustment.php');

    if ($response->failed()) {
        return response()->json(['error' => 'Failed to fetch API data'], 500);
    }

    $data = $response->json();

    if (!isset($data['data']) || !is_array($data['data'])) {
        return response()->json(['error' => 'Invalid API response format'], 422);
    }

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $skippedDetails = [];

    foreach ($data['data'] as $index => $item) {
        if (empty($item['id']) || empty($item['date']) || empty($item['product']) || !isset($item['quantity']) || !isset($item['type'])) {
            $skipped++;
            $skippedDetails[] = [
                'index' => $index,
                'reason' => 'Missing required fields',
                'data' => $item,
            ];
            continue;
        }

        $product = ProductsModel::where('name', 'LIKE', trim($item['product']))->first();
        if (!$product) {
            $skipped++;
            $skippedDetails[] = [
                'index' => $index,
                'reason' => "Product not found: '{$item['product']}'",
                'data' => $item,
            ];
            continue;
        }

        $godown = GodownModel::where('name', 'LIKE', trim($item['place']))->first();
        if (!$godown) {
            $skipped++;
            $skippedDetails[] = [
                'index' => $index,
                'reason' => "Godown/place not found: '{$item['place']}'",
                'data' => $item,
            ];
            continue;
        }

        // Convert type as requested
        $typeValue = $item['type'] == "1" ? 'loss' : 'extra';

        $adjustmentData = [
            'company_id' => 1,
            'adjustment_date' => $item['date'],
            'product_id' => $product->id,
            'quantity' => (float) $item['quantity'],
            'godown_id' => $godown->id,
            'type' => $typeValue,
        ];

        $adjustment = AdjustmentModel::find($item['id']);

        if ($adjustment) {
            // Check if any data differs before update
            $needsUpdate = false;
            foreach ($adjustmentData as $key => $value) {
                if ($adjustment->$key != $value) {
                    $needsUpdate = true;
                    break;
                }
            }

            if ($needsUpdate) {
                $adjustment->update($adjustmentData);
                $updated++;
            }
        } else {
            // Insert new record
            $adjustmentData['id'] = $item['id'];
            AdjustmentModel::create($adjustmentData);
            $inserted++;
        }
    }

    return response()->json([
        'message' => "Import completed",
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'total' => count($data['data']),
        'skipped_details' => $skippedDetails,
    ]);
}




}
