<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClosingStockModel extends Model
{
    //
    protected $table = 't_closing_stock';

    protected $fillable = [
        'company_id',
        'year',
        'godown_id',
        'product_id',
        'quantity',
        'value',
        'sold'
    ];
}
