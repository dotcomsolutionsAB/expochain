<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnProductsModel extends Model
{
    //
    protected $table = 't_purchase_return_products';

    protected $fillable = [
        'purchase_return_number',
        'product_id',
        'company_id',
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
        'godown'
    ];

    // Defining the relationship with PurchaseReturnModel (Parent)
    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturnModel::class, 'purchase_return_number', 'id');
    }
}
