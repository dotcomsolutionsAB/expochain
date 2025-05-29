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

        // STEP 1 : reset `opening stock`
        OpeningStockModel::where('year', $get_year)
                        ->where('company_id', Auth::user()->company_id)
                        ->where('product_id', $id)
                        ->update(['sold' => 0]);
        
        // STEP 2 : Reset sold items for all products in invoices within the date range
        PurchaseInvoiceProductsModel::whereHas('purchaseInvoice', function ($query) use ($start_date, $end_date) {
            $query->whereDate('purchase_invoice_date', '>=', $start_date)
                ->whereDate('purchase_invoice_date', '<=', $end_date);
        })
        ->where('product_id', $id)
        ->update(['sold' => 0]);
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
