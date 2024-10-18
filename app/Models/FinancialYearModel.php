<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialYearModel extends Model
{
    //
    protected $table = ' t_financial_year';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'opening_stock',
        'closing_stock',
    ];
}
