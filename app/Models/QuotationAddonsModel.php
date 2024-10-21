<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationAddonsModel extends Model
{
    //
    protected $table = 't_quotation_addons';

    protected $fillable = [
       'quotation_id',
        'name',
        'amount',
        'tax',
        'hsn',
        'cgst',
        'sgst', 
        'igst',
    ];
}
