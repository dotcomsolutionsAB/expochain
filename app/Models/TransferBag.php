<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferBag extends Model
{
    protected $table = 't_transfer_bag';

    // Allow mass-assignment for these fields
    protected $fillable = [
        'product_id', 'quantity', 'tb_date', 'godown_from', 'godown_to', 'log_user', 'log_date', 'company_id'
    ];

    // Automatically handle timestamps (created_at, updated_at)
    public $timestamps = true; // Eloquent will handle this for you

    public function productRelation()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id', 'id');
    }

    public function godownFromRelation()
    {
        return $this->belongsTo(GodownModel::class, 'godown_from', 'id');
    }

    public function godownToRelation()
    {
        return $this->belongsTo(GodownModel::class, 'godown_to', 'id');
    }
}

?>