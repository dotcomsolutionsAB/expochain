<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderProductsModel extends Model
{
    //
    protected $table = 't_purchase_order_products';

    protected $fillable = [
        'purchase_order_id',
        'company_id',
        'product_id',
        'product_name',
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
        'amount',
        'gross',
        'channel',
        'received',
        'short_closed'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderModel::class, 'purchase_order_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id');
    }

    public function godownRelation()
    {
        return $this->belongsTo(GodownModel::class, 'godown', 'id');
    }
}
