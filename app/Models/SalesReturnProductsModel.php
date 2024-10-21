<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnProductsModel extends Model
{
    //
    protected $table = 't_sales_return_products';

    protected $fillable = [
        'sales_return_id',
        'product_id',
        'product_name',
        'description',
        'description',
        'brand',
        'quantity',
        'unit',
        'price',
        'discount',
        'hsn',
        'tax',
        'cgst',
        'sgst',
        'igst',
        'godown',
    ];
}
