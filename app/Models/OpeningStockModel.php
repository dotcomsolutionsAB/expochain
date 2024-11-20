<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningStockModel extends Model
{
    //
    protected $table = 't_opening_stock';

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
