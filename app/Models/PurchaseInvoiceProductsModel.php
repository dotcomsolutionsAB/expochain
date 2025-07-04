<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceProductsModel extends Model
{
    //
    protected $table = 't_purchase_invoice_products';

    protected $fillable = [
        'company_id',
        'purchase_invoice_id', // Updated to match table column
        'product_id',
        'product_name',
        'description',  // Using `text` for large descriptions
        'quantity',
        'unit',
        'price',
        'discount',
        'discount_type', // Enum ('percentage', 'value')
        'hsn',
        'tax',
        'cgst',
        'sgst',
        'igst',
        'amount',  // Set default(0)
        'channel', // Set default(0)
        'godown',  // Nullable
        'returned', // Set default(0)
        'sold', // Set default(0)
    ];

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoiceModel::class, 'purchase_invoice_id', 'id');
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
