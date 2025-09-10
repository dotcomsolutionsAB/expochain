<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http; // For HTTP client
use Illuminate\Http\Request;

use App\Models\AdjustmentModel;
use App\Models\AssemblyModel;
use App\Models\AssemblyOperationModel;
use App\Models\AssemblyOperationProductsModel;
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
use App\Models\SupplierContactsModel;
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
            'Products' => ProductsModel::count(),
            'Clients' => ClientsModel::count(),
            'Suppliers' => SuppliersModel::count(),
            // Quotation & Quotation Products
            'Quotation' => [
                'count' => QuotationsModel::count(),
                'products' => QuotationProductsModel::count()
            ],
            // Sales Order & Sales Order Products
            'Sales Order' => [
                'count' => SalesOrderModel::count(),
                'products' => SalesOrderProductsModel::count()
            ],
            // Sales Invoice & Sales Invoice Products
            'Sales Invoice' => [
                'count' => SalesInvoiceModel::count(),
                'products' => SalesInvoiceProductsModel::count()
            ],
            'Sales Return' => [
                'count' => SalesReturnModel::count(),
                'products' => SalesReturnProductsModel::count()
            ],
            
            'Debit Note' => DebitNoteModel::count(),
            'Lot Info' => LotModel::count(),
            // Purchase bag
            'Purchase Back' => PurchaseBackModel::count(), // need to check purchase bag

            'Purchase Order' => [
                'count' => PurchaseOrderModel::count(),
                'products' => PurchaseOrderProductsModel::count()
            ],
            // Purchase Invoice & Purchase Invoice Products
            'Purchase Invoice' => [
                'count' => PurchaseInvoiceModel::count(),
                'products' => PurchaseInvoiceProductsModel::count()
            ],
            // Purchase Return & Purchase Return Products
            'Purchase Return' => [
                'count' => PurchaseReturnModel::count(),
                'products' => PurchaseReturnProductsModel::count()
            ],

            // Additional models
            'Credit Note' => CreditNoteModel::count(),
            'Assembly Combinations' => AssemblyModel::count(),
            // Assembly Operation & Assembly Operation Products
            'Assembly Operation' => [
                'count' => AssemblyOperationModel::count(),
                'products' => AssemblyOperationProductsModel::count()
            ],
            'Fabrication' => FabricationModel::count(),
            'Adjustments' => AdjustmentModel::count(),
            'Stock Transfer' => StockTransferModel::count(),
            // transfer back
            'Test Certificate' => TestCertificateModel::count(),
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
                                    <th>Sl No</th>
                                    <th>Voucher</th>
                                    <th>Count</th>
                                    <th>Products</th>
                                </tr>
                            </thead>
                            <tbody>';

                                $slNo = 1;
                                foreach ($counts as $model => $data) {
                                    if (is_array($data)) {
                                        $html .= '<tr><td>' . $slNo++ . '</td><td>' . htmlspecialchars($model) . '</td><td>' . htmlspecialchars($data['count']) . '</td><td>' . htmlspecialchars($data['products']) . '</td></tr>';
                                    } else {
                                        $html .= '<tr><td>' . $slNo++ . '</td><td>' . htmlspecialchars($model) . '</td><td>' . htmlspecialchars($data) . '</td><td>-</td></tr>';
                                    }
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
        $processedRecords = [];

        foreach ($data['data'] as $index => $item) {
            // Validate required fields including 'id'
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

            $typeValue = $item['type'] == "1" ? 'loss' : 'extra';

            $adjustmentData = [
                'id' => $item['id'], // manual assignment of PK
                'company_id' => 1,   // set as needed
                'adjustment_date' => $item['date'],
                'product_id' => $product->id,
                'quantity' => (float) $item['quantity'],
                'godown_id' => $godown->id,
                'type' => $typeValue,
            ];

            $adjustment = AdjustmentModel::find($item['id']);

            if ($adjustment) {
                // Compare fields to update only if changed
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
                $processedRecords[] = ['local_id' => $adjustment->id, 'api_id' => $item['id']];
            } else {
                AdjustmentModel::create($adjustmentData);
                $inserted++;
                $processedRecords[] = ['local_id' => $item['id'], 'api_id' => $item['id']];
            }
        }

        return response()->json([
            'message' => "Import completed",
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($data['data']),
            'skipped_details' => $skippedDetails,
            'processed_records' => $processedRecords,
        ]);
    }

    public function importFabrication()
    {
        $response = Http::get('https://expo.egsm.in/assets/custom/migrate/fabrication.php');

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch API data'], 500);
        }

        $data = $response->json();

        if (!isset($data['data']) || !is_array($data['data'])) {
            return response()->json(['error' => 'Invalid API response format'], 422);
        }

        $insertedMain = 0;
        $updatedMain = 0;
        $insertedProducts = 0;
        $updatedProducts = 0;
        $skipped = 0;
        $skippedDetails = [];

        // Placeholder values - change as needed
        $companyId = 1;
        $vendorId = 1;

        foreach ($data['data'] as $index => $item) {
            // Basic required fields validation
            if (empty($item['date']) || empty($item['product']) || !isset($item['quantity']) || empty($item['place'])) {
                $skipped++;
                $skippedDetails[] = [
                    'index' => $index,
                    'reason' => 'Missing required fields',
                    'data' => $item,
                ];
                continue;
            }

            // Find product_id by product name
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

            // Find godown_id by place
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

            // Determine Fabrication (main record) uniqueness criteria:
            // Let's assume uniqueness by fb_date + remarks (since invoice_no missing in API)
            $fbDate = $item['date'];
            $remarks = $item['comments'] ?? '';

            $fabrication = FabricationModel::where('fb_date', $fbDate)
                ->where('remarks', $remarks)
                ->where('company_id', $companyId)
                ->first();

            if (!$fabrication) {
                // Create new fabrication
                $fabrication = FabricationModel::create([
                    'company_id' => $companyId,
                    'vendor_id' => $vendorId,
                    'fb_date' => $fbDate,
                    'invoice_no' => null, // Not in API, set null or add logic
                    'remarks' => $remarks,
                    'fb_amount' => null, // Not in API, set null or add logic
                ]);
                $insertedMain++;
            } else {
                $updatedMain++;
                // Optional: update fabrication if you want here (e.g. remarks changed)
                // $fabrication->update([...]);
            }

            // Now handle FabricationProducts linked to this fabrication
            // Assuming uniqueness by fb_id + product_id + type + wastage (wastage is missing in API, so set null)
            $fabProdQuery = FabricationProductsModel::where('fb_id', $fabrication->id)
                ->where('product_id', $product->id)
                ->where('type', $item['type'])
                ->whereNull('wastage');

            $fabricationProduct = $fabProdQuery->first();

            $prodData = [
                'company_id' => $companyId,
                'fb_id' => $fabrication->id,
                'product_id' => $product->id,
                'quantity' => (float)$item['quantity'],
                'rate' => 0,      // Not in API
                'amount' => 0,    // Not in API
                'godown_id' => $godown->id,
                'remarks' => $remarks,
                'type' => $item['type'],
                'wastage' => null,   // Not in API
            ];

            if (!$fabricationProduct) {
                FabricationProductsModel::create($prodData);
                $insertedProducts++;
            } else {
                // Compare fields and update if changed (optional)
                $needsUpdate = false;
                foreach ($prodData as $key => $value) {
                    if ($fabricationProduct->$key != $value) {
                        $needsUpdate = true;
                        break;
                    }
                }
                if ($needsUpdate) {
                    $fabricationProduct->update($prodData);
                    $updatedProducts++;
                }
            }
        }

        return response()->json([
            'message' => 'Import completed',
            'fabrications_inserted' => $insertedMain,
            'fabrications_updated' => $updatedMain,
            'fabrication_products_inserted' => $insertedProducts,
            'fabrication_products_updated' => $updatedProducts,
            'skipped' => $skipped,
            'skipped_details' => $skippedDetails,
            'total' => count($data['data']),
        ]);
    }


}
