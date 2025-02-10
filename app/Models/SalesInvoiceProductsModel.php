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
        'channel',
        'returned',
        'profit',
        'purchase_invoice_id',
        'purchase_rate',
        'discount_type',
        'discount',
        'so_no',
        'purchase_invoice_products_id',
        'rate',
        'hsn',
        'tax',
        'cgst',
        'sgst',
        'igst',
        'godown',
    ];

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoiceModel::class, 'sales_invoice_id');
    }

}
