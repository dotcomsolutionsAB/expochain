<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjustmentModel extends Model
{
    //
    protected $table = 't_adjustments';

    protected $fillable = [
        'company_id',
        'adjustment_date',
        'product_id',
        'quantity',
        'godown_id',
        'type',
    ];
}
