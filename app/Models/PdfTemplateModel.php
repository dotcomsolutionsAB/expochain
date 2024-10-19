<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfTemplateModel extends Model
{
    //
    protected $table = ' t_pdf_template';

    protected $fillable = [
        'name',
        'phone_number',
        'mobile',
        'email',
        'address_line_1',
        'address_line_2',
        'city',
        'pincode',
        'state',
        'country',
        'gstin',
        'bank_number',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
        'header',
        'footer',
    ];
}
