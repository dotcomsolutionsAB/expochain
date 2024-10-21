<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnModel extends Model
{
    //
    protected $table = 't_sales_return';

    protected $fillable = [
        'client_id',
        'name',
        'sales_return_no',
        'sales_return_date',
        'sales_invoice_no',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'template',
        'status',
    ];
}
