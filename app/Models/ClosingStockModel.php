<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClosingStockModel extends Model
{
    //
    protected $table = 't_closing_stock';

    protected $fillable = [
        'company_id',
        'year',
        'godown_id',
        'product_id',
        'quantity',
        'value'
    ];

    public function godown()
    {
        return $this->belongsTo(GodownModel::class, 'godown_id');
    }
}
