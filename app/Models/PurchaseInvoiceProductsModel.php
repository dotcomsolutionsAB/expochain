<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceProductsModel extends Model
{
    //
    protected $table = 't_purchase_invoice_products';

    protected $fillable = [
        'purchase_invoice_number',
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
        'godown'
    ];
}
