<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OpeningStockModel;
use Carbon\Carbon;

class ResetController extends Controller
{
    //
    public function stock_calculation()
    {
        $currentDate = Carbon::now(); // Current date
        $year = $currentDate->year;

        dd($year);
        // OpeningStockModel::select('product_id', 'value')
        //                   ->where()
    }
}
