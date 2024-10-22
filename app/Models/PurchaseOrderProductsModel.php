<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderProductsModel extends Model
{
    //
    protected $table = 't_purchase_order_products';

    protected $fillable = [
        'purchase_order_number',
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

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderModel::class, 'purchase_order_number', 'id');
    }
}
