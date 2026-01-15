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
    // public function index()
    // {
    //     // Collect counts for all models here
    //     $counts = [
    //         'Products' => ProductsModel::count(),
    //         'Clients' => ClientsModel::count(),
    //         'Suppliers' => SuppliersModel::count(),
    //         // Quotation & Quotation Products
    //         'Quotation' => [
    //             'count' => QuotationsModel::count(),
    //             'products' => QuotationProductsModel::count()
    //         ],
    //         // Sales Order & Sales Order Products
    //         'Sales Order' => [
    //             'count' => SalesOrderModel::count(),
    //             'products' => SalesOrderProductsModel::count()
    //         ],
    //         // Sales Invoice & Sales Invoice Products
    //         'Sales Invoice' => [
    //             'count' => SalesInvoiceModel::count(),
    //             'products' => SalesInvoiceProductsModel::count()
    //         ],
    //         'Sales Return' => [
    //             'count' => SalesReturnModel::count(),
    //             'products' => SalesReturnProductsModel::count()
    //         ],
            
    //         'Debit Note' => DebitNoteModel::count(),
    //         'Lot Info' => LotModel::count(),
    //         // Purchase bag
    //         'Purchase Back' => PurchaseBackModel::count(), // need to check purchase bag

    //         'Purchase Order' => [
    //             'count' => PurchaseOrderModel::count(),
    //             'products' => PurchaseOrderProductsModel::count()
    //         ],
    //         // Purchase Invoice & Purchase Invoice Products
    //         'Purchase Invoice' => [
    //             'count' => PurchaseInvoiceModel::count(),
    //             'products' => PurchaseInvoiceProductsModel::count()
    //         ],
    //         // Purchase Return & Purchase Return Products
    //         'Purchase Return' => [
    //             'count' => PurchaseReturnModel::count(),
    //             'products' => PurchaseReturnProductsModel::count()
    //         ],

    //         // Additional models
    //         'Credit Note' => CreditNoteModel::count(),
    //         'Assembly Combinations' => AssemblyModel::count(),
    //         // Assembly Operation & Assembly Operation Products
    //         'Assembly Operation' => [
    //             'count' => AssemblyOperationModel::count(),
    //             'products' => AssemblyOperationProductsModel::count()
    //         ],
    //         'Fabrication' => FabricationModel::count(),
    //         'Adjustments' => AdjustmentModel::count(),
    //         'Stock Transfer' => StockTransferModel::count(),
    //         // transfer back
    //         'Test Certificate' => TestCertificateModel::count(),
    //     ];

    //     // Build HTML directly
    //     $html = '
    //             <!DOCTYPE html>
    //             <html lang="en">
    //                 <head>
    //                     <meta charset="UTF-8" />
    //                     <meta name="viewport" content="width=device-width, initial-scale=1" />
    //                     <title>Stats Overview</title>
    //                     <style>
    //                         body { font-family: Arial, sans-serif; margin: 40px auto; max-width: 700px; background: #f9f9f9; }
    //                         table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    //                         th, td { padding: 12px 20px; border: 1px solid #ddd; text-align: left; }
    //                         th { background-color: #007bff; color: white; }
    //                         caption { font-size: 1.5em; margin-bottom: 10px; font-weight: bold; color: #333; }
    //                         tbody tr:hover { background-color: #f1f7ff; }
    //                     </style>
    //                 </head>
    //                 <body>
    //                     <table>
    //                         <caption>Database Table Counts</caption>
    //                         <thead>
    //                             <tr>
    //                                 <th>Sl No</th>
    //                                 <th>Voucher</th>
    //                                 <th>Count</th>
    //                                 <th>Products</th>
    //                             </tr>
    //                         </thead>
    //                         <tbody>';

    //                             $slNo = 1;
    //                             foreach ($counts as $model => $data) {
    //                                 if (is_array($data)) {
    //                                     $html .= '<tr><td>' . $slNo++ . '</td><td>' . htmlspecialchars($model) . '</td><td>' . htmlspecialchars($data['count']) . '</td><td>' . htmlspecialchars($data['products']) . '</td></tr>';
    //                                 } else {
    //                                     $html .= '<tr><td>' . $slNo++ . '</td><td>' . htmlspecialchars($model) . '</td><td>' . htmlspecialchars($data) . '</td><td>-</td></tr>';
    //                                 }
    //                             }

    //                             $html .= '
    //                         </tbody>
    //                     </table>
    //                 </body>
    //             </html>
    //         ';

    //     return response($html, 200)->header('Content-Type', 'text/html');
    // }

    public function index()
    {
        // ✅ New DB counts
        $newCounts = [
            'Products' => ProductsModel::count(),
            'Clients' => ClientsModel::count(),
            'Suppliers' => SuppliersModel::count(),
            'Quotation' => [
                'count' => QuotationsModel::count(),
                'products' => QuotationProductsModel::count()
            ],
            'Sales Order' => [
                'count' => SalesOrderModel::count(),
                'products' => SalesOrderProductsModel::count()
            ],
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
            'Purchase Back' => PurchaseBackModel::count(),

            'Purchase Order' => [
                'count' => PurchaseOrderModel::count(),
                'products' => PurchaseOrderProductsModel::count()
            ],
            'Purchase Invoice' => [
                'count' => PurchaseInvoiceModel::count(),
                'products' => PurchaseInvoiceProductsModel::count()
            ],
            'Purchase Return' => [
                'count' => PurchaseReturnModel::count(),
                'products' => PurchaseReturnProductsModel::count()
            ],
            'Credit Note' => CreditNoteModel::count(),
            'Assembly Combinations' => AssemblyModel::count(),
            'Assembly Operation' => [
                'count' => AssemblyOperationModel::count(),
                'products' => AssemblyOperationProductsModel::count()
            ],
            'Fabrication' => [
                'count' => FabricationModel::count(),
                'products' => FabricationProductsModel::count(),
            ],
            'Adjustments' => AdjustmentModel::count(),
            'Stock Transfer' => StockTransferModel::count(),
            'Test Certificate' => TestCertificateModel::count(),
        ];

        // ✅ Old DB counts (from external HTML page)
        $oldApiUrl = 'https://expo.egsm.in/assets/custom/migrate/stats.php';
        $oldRows = []; // each row: ['sl' => 1, 'voucher' => 'Product', 'count' => '4883', 'products' => '']

        try {
            $resp = Http::timeout(10)->get($oldApiUrl);

            if ($resp->successful()) {
                $htmlBody = $resp->body();

                // Parse tbody rows from old HTML
                libxml_use_internal_errors(true);

                $dom = new \DOMDocument();
                $dom->loadHTML($htmlBody);

                $xpath = new \DOMXPath($dom);
                $trs = $xpath->query("//table//tbody//tr");

                foreach ($trs as $tr) {
                    $tds = $tr->getElementsByTagName('td');
                    if ($tds->length >= 3) {
                        $oldRows[] = [
                            'sl'       => trim($tds->item(0)->textContent ?? ''),
                            'voucher'  => trim($tds->item(1)->textContent ?? ''),
                            'count'    => trim($tds->item(2)->textContent ?? ''),
                            'products' => trim($tds->item(3)->textContent ?? ''),
                        ];
                    }
                }

                libxml_clear_errors();
            }
        } catch (\Exception $e) {
            // if old api fails, we'll just show empty oldRows and show a note in UI
            \Log::error("Old stats fetch failed: " . $e->getMessage());
        }

        // ✅ Normalize voucher names for comparison
        $voucherMapping = [
            'Product' => 'Products',
            'Products' => 'Products',
            'Clients' => 'Clients',
            'Suppliers' => 'Suppliers',
            'Quotation' => 'Quotation',
            'Sales order' => 'Sales Order',
            'Sales Order' => 'Sales Order',
            'Sales Invoice' => 'Sales Invoice',
            'Sales Return' => 'Sales Return',
            'Debit note' => 'Debit Note',
            'Debit Note' => 'Debit Note',
            'Lot info' => 'Lot Info',
            'Lot Info' => 'Lot Info',
            'Purchase bag' => 'Purchase Back',
            'Purchase Back' => 'Purchase Back',
            'Purchase order' => 'Purchase Order',
            'Purchase Order' => 'Purchase Order',
            'Purchase Invoice' => 'Purchase Invoice',
            'Purchase Return' => 'Purchase Return',
            'Credit note' => 'Credit Note',
            'Credit Note' => 'Credit Note',
            'Assembly combination' => 'Assembly Combinations',
            'Assembly Combinations' => 'Assembly Combinations',
            'Assembly operation' => 'Assembly Operation',
            'Assembly Operation' => 'Assembly Operation',
            'Fabrication' => 'Fabrication',
            'Adjustments' => 'Adjustments',
            'Stock transfer' => 'Stock Transfer',
            'Stock Transfer' => 'Stock Transfer',
            'Transfer bag' => 'Transfer Bag',
            'Test certificate' => 'Test Certificate',
            'Test Certificate' => 'Test Certificate',
        ];

        // ✅ Build comparison data
        $comparisonData = [];
        $slNo = 1;
        
        foreach ($newCounts as $model => $data) {
            $newCount = is_array($data) ? $data['count'] : $data;
            $newProducts = is_array($data) ? $data['products'] : null;
            
            // Find matching old row
            $oldRow = null;
            foreach ($oldRows as $row) {
                $normalizedOld = $voucherMapping[trim($row['voucher'])] ?? trim($row['voucher']);
                if ($normalizedOld === $model) {
                    $oldRow = $row;
                    break;
                }
            }
            
            $oldCount = $oldRow ? (int) str_replace(',', '', $oldRow['count']) : null;
            $oldProducts = $oldRow ? ($oldRow['products'] !== '' && $oldRow['products'] !== '-' ? (int) str_replace(',', '', $oldRow['products']) : null) : null;
            
            $countDiff = $oldCount !== null ? $newCount - $oldCount : null;
            $productsDiff = ($oldProducts !== null && $newProducts !== null) ? $newProducts - $oldProducts : null;
            
            $comparisonData[] = [
                'sl' => $slNo++,
                'voucher' => $model,
                'new_count' => $newCount,
                'old_count' => $oldCount,
                'count_diff' => $countDiff,
                'new_products' => $newProducts,
                'old_products' => $oldProducts,
                'products_diff' => $productsDiff,
            ];
        }

        // ✅ Build HTML with comparison table
        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Database Migration Stats Comparison</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 30px 20px;
                    min-height: 100vh;
                }
                .container {
                    max-width: 1400px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    overflow: hidden;
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .header h1 {
                    font-size: 32px;
                    font-weight: 700;
                    margin-bottom: 10px;
                }
                .header p {
                    font-size: 16px;
                    opacity: 0.9;
                }
                .summary {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    padding: 30px;
                    background: #f8f9fa;
                    border-bottom: 2px solid #e9ecef;
                }
                .summary-card {
                    background: white;
                    padding: 20px;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .summary-card .label {
                    font-size: 14px;
                    color: #6c757d;
                    margin-bottom: 8px;
                    font-weight: 500;
                }
                .summary-card .value {
                    font-size: 28px;
                    font-weight: 700;
                    color: #212529;
                }
                .summary-card .value.positive { color: #28a745; }
                .summary-card .value.negative { color: #dc3545; }
                .summary-card .value.neutral { color: #6c757d; }
                .table-wrapper {
                    padding: 30px;
                    overflow-x: auto;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    background: white;
                    font-size: 14px;
                }
                thead {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                th {
                    padding: 16px 12px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                td {
                    padding: 14px 12px;
                    border-bottom: 1px solid #e9ecef;
                }
                tbody tr {
                    transition: background-color 0.2s;
                }
                tbody tr:hover {
                    background-color: #f8f9fa;
                }
                tbody tr.match { background-color: #d4edda; }
                tbody tr.mismatch { background-color: #f8d7da; }
                tbody tr.new-record { background-color: #d1ecf1; }
                .voucher-name {
                    font-weight: 600;
                    color: #212529;
                }
                .count-cell {
                    text-align: right;
                    font-family: "Courier New", monospace;
                    font-weight: 500;
                }
                .diff-cell {
                    text-align: center;
                    font-weight: 600;
                }
                .diff-positive {
                    color: #28a745;
                }
                .diff-negative {
                    color: #dc3545;
                }
                .diff-zero {
                    color: #6c757d;
                }
                .diff-na {
                    color: #adb5bd;
                    font-style: italic;
                }
                .badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .badge-match { background: #d4edda; color: #155724; }
                .badge-mismatch { background: #f8d7da; color: #721c24; }
                .badge-new { background: #d1ecf1; color: #0c5460; }
                .note {
                    padding: 20px 30px;
                    background: #f8f9fa;
                    border-top: 2px solid #e9ecef;
                    font-size: 13px;
                    color: #6c757d;
                }
                .note a {
                    color: #667eea;
                    text-decoration: none;
                }
                .note a:hover {
                    text-decoration: underline;
                }
                @media (max-width: 768px) {
                    .header h1 { font-size: 24px; }
                    .summary { grid-template-columns: 1fr; }
                    .table-wrapper { padding: 15px; }
                    table { font-size: 12px; }
                    th, td { padding: 10px 8px; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📊 Database Migration Stats Comparison</h1>
                    <p>Comparing New Database vs Old Database Record Counts</p>
                </div>
                
                <div class="summary">';

        // Calculate summary statistics
        $totalMatches = 0;
        $totalMismatches = 0;
        $totalNew = 0;
        $totalCountDiff = 0;
        
        foreach ($comparisonData as $row) {
            if ($row['old_count'] === null) {
                $totalNew++;
            } elseif ($row['count_diff'] == 0) {
                $totalMatches++;
            } else {
                $totalMismatches++;
            }
            if ($row['count_diff'] !== null) {
                $totalCountDiff += abs($row['count_diff']);
            }
        }

        $html .= '
                    <div class="summary-card">
                        <div class="label">Total Records</div>
                        <div class="value">' . count($comparisonData) . '</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">✅ Matches</div>
                        <div class="value positive">' . $totalMatches . '</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">⚠️ Mismatches</div>
                        <div class="value negative">' . $totalMismatches . '</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">🆕 New Records</div>
                        <div class="value neutral">' . $totalNew . '</div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Voucher Type</th>
                                <th style="text-align: right;">New DB<br>Count</th>
                                <th style="text-align: right;">Old DB<br>Count</th>
                                <th style="text-align: center;">Difference</th>
                                <th style="text-align: right;">New DB<br>Products</th>
                                <th style="text-align: right;">Old DB<br>Products</th>
                                <th style="text-align: center;">Products<br>Difference</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>';

        foreach ($comparisonData as $row) {
            // Determine row class and status
            $rowClass = '';
            $statusBadge = '';
            
            if ($row['old_count'] === null) {
                $rowClass = 'new-record';
                $statusBadge = '<span class="badge badge-new">New</span>';
            } elseif ($row['count_diff'] == 0) {
                $rowClass = 'match';
                $statusBadge = '<span class="badge badge-match">Match</span>';
            } else {
                $rowClass = 'mismatch';
                $statusBadge = '<span class="badge badge-mismatch">Diff</span>';
            }

            // Format difference
            $countDiffHtml = '';
            if ($row['count_diff'] === null) {
                $countDiffHtml = '<span class="diff-na">N/A</span>';
            } elseif ($row['count_diff'] > 0) {
                $countDiffHtml = '<span class="diff-positive">+' . number_format($row['count_diff']) . '</span>';
            } elseif ($row['count_diff'] < 0) {
                $countDiffHtml = '<span class="diff-negative">' . number_format($row['count_diff']) . '</span>';
            } else {
                $countDiffHtml = '<span class="diff-zero">0</span>';
            }

            // Format products difference
            $productsDiffHtml = '';
            if ($row['products_diff'] === null) {
                $productsDiffHtml = '<span class="diff-na">-</span>';
            } elseif ($row['products_diff'] > 0) {
                $productsDiffHtml = '<span class="diff-positive">+' . number_format($row['products_diff']) . '</span>';
            } elseif ($row['products_diff'] < 0) {
                $productsDiffHtml = '<span class="diff-negative">' . number_format($row['products_diff']) . '</span>';
            } else {
                $productsDiffHtml = '<span class="diff-zero">0</span>';
            }

            $html .= '<tr class="' . $rowClass . '">
                <td>' . $row['sl'] . '</td>
                <td><span class="voucher-name">' . htmlspecialchars($row['voucher']) . '</span></td>
                <td class="count-cell">' . number_format($row['new_count']) . '</td>
                <td class="count-cell">' . ($row['old_count'] !== null ? number_format($row['old_count']) : '<span class="diff-na">-</span>') . '</td>
                <td class="diff-cell">' . $countDiffHtml . '</td>
                <td class="count-cell">' . ($row['new_products'] !== null ? number_format($row['new_products']) : '-') . '</td>
                <td class="count-cell">' . ($row['old_products'] !== null ? number_format($row['old_products']) : '-') . '</td>
                <td class="diff-cell">' . $productsDiffHtml . '</td>
                <td style="text-align: center;">' . $statusBadge . '</td>
            </tr>';
        }

        $html .= '
                        </tbody>
                    </table>
                </div>

                <div class="note">
                    <strong>Source:</strong> Old database stats fetched from <a href="' . htmlspecialchars($oldApiUrl) . '" target="_blank">' . htmlspecialchars($oldApiUrl) . '</a><br>
                    <strong>Legend:</strong> 
                    <span class="badge badge-match">Match</span> = Counts match exactly | 
                    <span class="badge badge-mismatch">Diff</span> = Counts differ | 
                    <span class="badge badge-new">New</span> = Record exists only in new database
                </div>
            </div>
        </body>
        </html>';

        return response($html, 200)->header('Content-Type', 'text/html');
    }

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
