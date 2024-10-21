<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationTermsModel extends Model
{
    //
    protected $table = 't_quotation_terms';

    protected $fillable = [
        'quotation_id',
        'name',
        'value',
    ];
}
