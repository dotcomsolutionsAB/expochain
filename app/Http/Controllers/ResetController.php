<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OpeningStockModel;
use App\Models\ClosingStockModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\ResetQueueModel;
use App\Models\GodownModel;
use App\Models\SalesInvoiceModel;
use Carbon\Carbon;
use Auth;
use DB;

class ResetController extends Controller
{
    //

    public function make_reset_queue(Request $request)
    {
        // Check if the record exists with status 0
        $existingQueue = ResetQueueModel::where([
            'product_id' => $request->input('product_id'),
            'company_id' => Auth::user()->company_id,
        ])->first();

        if ($existingQueue != null) {
            if ($existingQueue->status == '0') {
                // Skip if the status is 0
                return response()->json([
                    'message' => 'Product already exists in the reset queue with status 0. Skipped.',
                ], 200);
            } 
        }

        // Create a new record if status not 0
        $newQueue = ResetQueueModel::create([
            'product_id' => $request->input('product_id'),
            'company_id' => Auth::user()->company_id,
            'status' => '0', // Default status for new records
        ]);

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Product added to the reset queue successfully!',
            'reset_queue' => $newQueue,
        ], 201);
    }

    public function stock_calculation($id)
    {
        $start_date = "2024-04-01";
        $end_date = Carbon::now()->format('Y-m-d');
        $get_year = 6;

        DB::transaction(function() use ($start_date, $end_date, $get_year, $id) {
            // 🔹 STEP 1 : Reset 'sold' in opening stock for this product and year
            OpeningStockModel::where('year', $get_year)
                ->where('company_id', Auth::user()->company_id)
                ->where('product_id', $id)
                ->update(['sold' => 0]);

            // 🔹 STEP 2 : Reset 'sold' in purchase invoice products within the date range for this product
            PurchaseInvoiceProductsModel::whereHas('purchaseInvoice', function ($query) use ($start_date, $end_date) {
                    $query->whereDate('purchase_invoice_date', '>=', $start_date)
                        ->whereDate('purchase_invoice_date', '<=', $end_date);
                })
                ->where('product_id', $id)
                ->update(['sold' => 0]);

            // 🔹 STEP 3 : Reset sales invoice products fields for this product within the date range
            SalesInvoiceProductsModel::whereHas('salesInvoice', function ($query) use ($start_date, $end_date) {
                    $query->whereDate('sales_invoice_date', '>=', $start_date)
                        ->whereDate('sales_invoice_date', '<=', $end_date);
                })
                ->where('product_id', $id)  // 👈 Add filter for this product
                ->update([
                    'profit' => 0,
                    'returned' => 0,
                    'purchase_invoice_id' => null,
                    'purchase_rate' => null,
                ]);

            // 🔹 STEP 4 : Traverse sales invoices for this product within date range
            $salesInvoices = SalesInvoiceModel::with(['products' => function($query) use ($id) {
                    $query->where('product_id', $id);
                }])
                ->whereBetween('sales_invoice_date', [$start_date, $end_date])
                ->orderBy('sales_invoice_date')
                ->get();

            foreach ($salesInvoices as $invoice) {
                foreach ($invoice->products as $saleProduct) {
                    $productId = $saleProduct->product_id;

                    if($productId != $id) {
                        continue; // Skip if product ID does not match
                    }

                    $godownId = $saleProduct->godown;
                    $remainingQty = $saleProduct->quantity;
                    $purchaseDetails = [];
                    $totalPurchaseCost = 0;

                    // 🔹 Step 4A: Exhaust Opening Stock FIRST for matching godown
                    $openingStocks = OpeningStockModel::where('product_id', $productId)
                        ->where('year', $get_year)
                        ->where('company_id', Auth::user()->company_id)
                        ->where('godown_id', $godownId)
                        ->whereColumn('quantity', '>', DB::raw('sold'))
                        ->orderBy('created_at')
                        ->get();

                    foreach ($openingStocks as $opening) {
                        if ($remainingQty <= 0) break;

                        $availableQty = $opening->quantity - $opening->sold;
                        $usedQty = min($availableQty, $remainingQty);

                        $opening->sold += $usedQty;
                        $opening->save();

                        // 🔹 Add detailed entry for opening stock
                        $purchaseDetails[] = [
                            'id' => $opening->id,
                            'type' => 'opening_stock',
                            'quantity' => $usedQty,
                        ];

                        $totalPurchaseCost += $usedQty * $opening->value;
                        $remainingQty -= $usedQty;
                    }

                    // 🔹 Step 4B: Then use Purchase Invoices with matching godown
                    if ($remainingQty > 0) {
                        $purchaseEntries = PurchaseInvoiceProductsModel::where('product_id', $productId)
                            ->where('godown', $godownId)
                            ->whereColumn('quantity', '>', DB::raw('sold'))
                            ->join('t_purchase_invoice', 't_purchase_invoice_products.purchase_invoice_id', '=', 't_purchase_invoice.id')
                            ->whereBetween('t_purchase_invoice.purchase_invoice_date', [$start_date, $end_date])
                            ->orderBy('t_purchase_invoice.purchase_invoice_date')
                            ->select('t_purchase_invoice_products.*', 't_purchase_invoice.purchase_invoice_date')
                            ->get();

                        foreach ($purchaseEntries as $purchase) {
                            if ($remainingQty <= 0) break;

                            $availableQty = $purchase->quantity - $purchase->sold;
                            $usedQty = min($availableQty, $remainingQty);

                            $purchase->sold += $usedQty;
                            $purchase->save();

                            // 🔹 Add detailed entry for purchase
                            $purchaseDetails[] = [
                                'id' => $purchase->id,
                                'type' => 'purchase',
                                'quantity' => $usedQty,
                            ];
                            $totalPurchaseCost += $usedQty * $purchase->price;
                            $remainingQty -= $usedQty;
                        }
                    }

                    // 🔹 Step 4C: Update SalesInvoiceProduct with calculations
                    $saleProduct->purchase_invoice_id = $purchaseDetails ? json_encode($purchaseDetails) : null;
                    $saleProduct->purchase_rate = $totalPurchaseCost ?: null;
                    $saleProduct->profit = ($saleProduct->amount ?? 0) - ($totalPurchaseCost ?: 0);
                    $saleProduct->save();
                }
            }
        });
    }



    public function reset_product(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:t_products,id'
        ]);

        $companyId = auth()->user()->company_id;
        $productId = $request->product_id;

        // Step 1: Fetch all godowns for the company
        $godowns = GodownModel::where('company_id', $companyId)->get(['id', 'name']);

        // Step 2: Fetch godown-wise stock and value
        $stockSummary = OpeningStockModel::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->selectRaw('godown_id, SUM(quantity) as total_quantity, SUM(quantity * value) as stock_value')
            ->groupBy('godown_id')
            ->get()
            ->keyBy('godown_id');

        $godownData = $godowns->map(function ($godown) use ($stockSummary) {
            $entry = $stockSummary->get($godown->id);
            return [
                'godown_id' => $godown->id,
                'godown_name' => $godown->name,
                'quantity' => $entry->total_quantity ?? 0,
                'stock_value' => $entry->stock_value ?? 0,
            ];
        });

        // Step 3: Update PurchaseInvoiceProductsModel stock = quantity
        $purchaseItems = PurchaseInvoiceProductsModel::where('company_id', $companyId)
        ->where('product_id', $productId)
        ->whereHas('purchaseInvoice', function ($query) {
            $query->whereDate('purchase_invoice_date', '>=', '2024-04-01');
        })
        ->with(['purchaseInvoice:id,purchase_invoice_date']) // Eager load invoice date
        ->get();

        // Initialize counters and details

        $updatedCount = 0;
        $purchaseUpdateDetails = [];

        foreach ($purchaseItems as $item) {
            $item->stock = $item->quantity;
            $item->save();
            $updatedCount++;

            $purchaseUpdateDetails[] = [
                'purchase_invoice_date' => $item->purchaseInvoice->purchase_invoice_date,
                'godown' => $item->godown,
                'quantity' => $item->quantity,
            ];
        }

        // Step 4: Fetch Sales data
        $salesItems = SalesInvoiceProductsModel::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->whereHas('salesInvoice', function ($query) {
                $query->whereDate('sales_invoice_date', '>=', '2024-04-01');
            })
            ->with(['salesInvoice:id,sales_invoice_date']) // Eager load invoice date
            ->get();

        $salesData = $salesItems->map(function ($item) {
            return [
                'sales_invoice_date' => $item->salesInvoice->sales_invoice_date,
                'godown' => $item->godown,
                'quantity' => $item->quantity,
            ];
        });

        return response()->json([
            'message' => 'Reset completed successfully.',
            'godown_stock' => $godownData,
            'purchase_stock_updated' => $updatedCount,
            'purchase_update_details' => $purchaseUpdateDetails,
            'sales_invoice_data' => $salesData,
        ]);    
    }

}
