<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderProductsModel extends Model
{
    //
    protected $table = 't_sales_order_products';

    protected $fillable = [
        'sales_order_id',
        'company_id',
        'product_id',
        'product_name',
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
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrderModel::class, 'sales_order_id');
    }

}
