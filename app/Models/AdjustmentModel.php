<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjustmentModel extends Model
{
    //
    protected $table = 't_adjustments';

    protected $fillable = [
        'id',
        'company_id',
        'adjustment_date',
        'product_id',
        'quantity',
        'godown_id',
        'type',
    ];

    public function productRelation()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id');
    }

    public function godownRelation()
    {
        return $this->belongsTo(GodownModel::class, 'godown_id');
    }
}
