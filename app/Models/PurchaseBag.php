<?php

// app/Models/PurchaseBag.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBag extends Model
{
    protected $table = 't_purchase_bag';

    protected $fillable = [
        'product',
        'group',
        'category',
        'sub_category',
        'quantity',
        'pb_date',
        'temp',
        'log_user',
        'company_id',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'pb_date'    => 'date',
        'temp'       => 'integer',
        'category'   => 'integer',
        'sub_category' => 'integer',
        'company_id' => 'integer',
    ];
}

?>