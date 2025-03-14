<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBackModel extends Model
{
    //
    protected $table = 't_purchase_backs';

    protected $fillable = [
        'product',
        'company_id',
        'quantity',
        'date',
        'log_user',
    ];
}
