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

        // $get_records = OpeningStockModel::select('product_id', 'quantity')
        //                                 ->where('year', $get_year)
        //                                 ->where('company_id', Auth::user()->company_id)
        //                                 ->get();
        OpeningStockModel::where('year', $get_year)
                        ->where('company_id', Auth::user()->company_id)
                        ->where('product_id', $id)
                        ->update(['sold' => 0]);

        // foreach($get_records as $records)
        // {
        PurchaseInvoiceProductsModel::where('product_id', $id)->update(['sold' => 0]);

        $get_sales_invoice = SalesInvoiceProductsModel::select('quantity', 'purchase_invoice_products_id')
                                                        ->where('product_id', $id)
                                                        ->where('company_id', Auth::user()->company_id)
                                                        ->first();

                                        // o/s sold e add hobe quantity from SalesInvoiceProductsModel

        // update records to `opening_stock` at `sold`
        OpeningStockModel::where('product_id', $id)
                            ->where('company_id', Auth::user()->company_id)
                            ->update(['sold' => ($get_sales_invoice->quantity)]);

        $result = OpeningStockModel::where('product_id', $id)
                                    ->where('company_id', Auth::user()->company_id)
                                    ->whereColumn('quantity', 'sold')
                                    ->get();

        if($result != null)
        {
            PurchaseInvoiceProductsModel::where('product_id', $id)
                                         ->where('company_id', Auth::user()->company_id) 
                                         ->update(['sold' => ($get_sales_invoice->quantity)]);
        }
    }
}
