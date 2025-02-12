<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceProductsModel extends Model
{
    //
    protected $table = 't_sales_invoice_products';

    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'company_id',
        'product_name',
        'description',
        // 'brand',
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
        'so_id',
        'return',
        'profit',
        'purchase_invoice_id',
        'purchase_rate'
    ];

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoiceModel::class, 'sales_invoice_id');
    }

}
