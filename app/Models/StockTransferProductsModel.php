<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferProductsModel extends Model
{
    //
    protected $table = 't_stock_transfer_products';

    protected $fillable = [
        'transfer_id',
        'product_id',
        'company_id',
        'product_name',
        'description',
        'quantity',
        'unit',
        'status'
    ];
}
