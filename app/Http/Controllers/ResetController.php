<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OpeningStockModel;
use App\Models\ClosingStockModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\ResetQueueModel;
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
        $currentDate = Carbon::now(); // Current date
        $year = $currentDate->year;
        $next_year = $year + 1;

        $get_year = $year . '-' . $next_year;

        // opening stock quantity
        $opening_stock = OpeningStockModel::select('quantity')
                          ->where('company_id', Auth::user()->company_id)
                          ->where('product_id', $id)
                          ->first();
        
        $opening_stock_quantity = $opening_stock ? $opening_stock->quantity : 0; // Safely handle null

        // reset `opening stock`
        OpeningStockModel::where('year', $get_year)
                        ->where('company_id', Auth::user()->company_id)
                        ->where('product_id', $id)
                        ->update(['sold' => 0]);
        
        // reset sold items
        PurchaseInvoiceProductsModel::where('product_id', $id)->update(['sold' => 0]);
        
        // Fetch sold items
        $sales = SalesInvoiceProductsModel::select(DB::raw('SUM(quantity) as total_sold'))
                                    ->where('company_id', Auth::user()->company_id)
                                    ->where('product_id', $productId)
                                    ->groupBy('product_id')
                                    ->first();

        $total_sold = $sales ? $sales->total_sold : 0; // Safely handle null

        // update records to `opening_stock` at `sold`
        OpeningStockModel::where('product_id', $id)
                            ->where('company_id', Auth::user()->company_id)
                            ->update(['sold' => min($total_sold, $opening_stock_quantity)]);

        // update records to `purchase_invoice_stock` at `sold`
        PurchaseInvoiceProductsModel::where('product_id', $id)
                                    ->where('company_id', Auth::user()->company_id)
                                    ->update(['sold' => $total_sold]);

        // Check and update `closing_stock`
        if ($sales->total_sold > $opening_stock_quantity) {

            $left_sell_amount = ($total_sold) - ($opening_stock_quantity);

            ClosingStockModel::where('year', $get_year)
                        ->where('company_id', Auth::user()->company_id)
                        ->where('product_id', $id)
                        ->update(['sold' => $left_sell_amount]);
        }
    }

    public function reset_product(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id'
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
        $updatedCount = PurchaseInvoiceProductsModel::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->whereHas('purchaseInvoice', function ($query) {
                $query->whereDate('purchase_invoice_date', '>=', '2024-04-01');
            })
            ->update([
                'stock' => DB::raw('quantity')
            ]);

        // Step 4: Return SalesInvoiceModel data
        $sales = SalesInvoiceModel::where('company_id', $companyId)
            ->whereDate('sales_invoice_date', '>=', '2024-04-01')
            ->whereHas('salesInvoiceProducts', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->get();

        return response()->json([
            'message' => 'Reset completed successfully.',
            'godown_stock' => $godownData,
            'purchase_stock_updated' => $updatedCount,
            'sales_invoices' => $sales
        ]);
    }

}
