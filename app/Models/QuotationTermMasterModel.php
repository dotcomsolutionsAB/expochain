<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationTermMasterModel extends Model
{
    //
    protected $table = 't_quotation_term_masters';

    protected $fillable = [
        'company_id',
        'order',
        'name',
        'default_value',
        'type',
    ];
}
