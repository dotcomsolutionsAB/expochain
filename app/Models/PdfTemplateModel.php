<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfTemplateModel extends Model
{
    //
    protected $table = ' t_pdf_template';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'description',
        'type',
        'brand',
        'category',
        'sub_category',
        'cost_price',
        'sale_price',
        'unit',
        'hsn',
        'tax',
    ];
}
