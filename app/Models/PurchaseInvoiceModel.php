<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceModel extends Model
{
    //
    protected $table = 't_purchase_invoice';

    protected $fillable = [
        'supplier_id',
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'pincode',
        'state',
        'country',
        'purchase_invoice_no',
        'purchase_invoice_date',
        'purchase_order_no',
        'cgst',
        'sgst',
        'igst',
        'currency',
        'template',
        'status'
    ];
}
