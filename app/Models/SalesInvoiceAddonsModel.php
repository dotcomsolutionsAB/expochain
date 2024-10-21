<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceAddonsModel extends Model
{
    //
    protected $table = 't_sales_invoice_addons';

    protected $fillable = [
       'sales_invoice_id',
        'name',
        'amount',
        'tax',
        'hsn',
        'cgst',
        'sgst', 
        'igst',
    ];
}
