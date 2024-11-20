<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OpeningStockModel;
use App\Models\ClosingStockModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\SalesInvoiceProductsModel;
use Carbon\Carbon;
use Auth;

class ResetController extends Controller
{
    //
    public function stock_calculation()
    {
        $currentDate = Carbon::now(); // Current date
        $year = $currentDate->year;
        $next_year = $year + 1;

        $get_year = $year . '-' . $next_year;

        $get_records = OpeningStockModel::select('product_id', 'quantity')
                          ->where('year', $get_year)
                          ->where('company_id', Auth::user()->company_id)
                          ->get();

        foreach($get_records as $records)
        {
            PurchaseInvoiceProductsModel::where('product_id', $records->product_id)->update(['sold' => 0]);

            $get_sales_invoice = SalesInvoiceProductsModel::select('quantity', 'purchase_invoice_products_id')
                                                            ->where('product_id', $records->product_id)
                                                            ->where('company_id', Auth::user()->company_id)
                                                            ->first();

                                         
            ClosingStockModel::where('product_id', $records->product_id)
                                ->where('company_id', Auth::user()->company_id)
                                ->update(['quantity' => ($records->quantity -$get_sales_invoice->quantity)]);
        }
    }
}
