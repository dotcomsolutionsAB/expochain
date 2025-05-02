<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceProductsModel extends Model
{
    //
    protected $table = 't_sales_invoice_products';

    protected $fillable = [
        'sales_invoice_id',
        'company_id',
        'product_id',
        'product_name',
        'description',  // Now properly mapped to `text`
        'quantity',
        'unit',
        'price',
        'discount',
        'discount_type', // Now supports 'percentage' or 'value'
        'hsn',
        'tax',
        'cgst',
        'sgst',
        'igst',
        'amount',
        'channel',  // Added based on schema, nullable
        'godown',   // Added based on schema, nullable
        'so_id',    // Changed to match `bigInteger`
        'returned', // Matched schema, default(0)
        'profit',   // Matched schema, default(0)
        'purchase_invoice_id',  // Matched schema, default(0)
        'purchase_rate',  // Matched schema, default(0.00)
    ];

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoiceModel::class, 'sales_invoice_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'id');
    }

    public function godownRelation()
    {
        return $this->belongsTo(GodownModel::class, 'godown', 'id');
    }
}
