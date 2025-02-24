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
        'company_id',
        'product_name',
        'description',
        'description',
        'quantity',
        'unit',
        'price',
        'discount',
        'discount_type',
        'hsn',
        'tax',
        'cgst',
        'sgst',
        'igst',
        'godown',
    ];

    public function salesreturn()
    {
        return $this->belongsTo(SalesReturnModel::class, 'sales_return_id', 'id');
    }
}
