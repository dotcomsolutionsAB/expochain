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
        'company_id',
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
        'amount'
    ];

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoiceModel::class, 'purchase_invoice_number', 'id');
    }
}
