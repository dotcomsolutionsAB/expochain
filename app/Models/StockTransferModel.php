<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferModel extends Model
{
    //
    protected $table = 't_stock_transfer';

    protected $fillable = [
        'transfer_id',
        'godown_from',
        'godown_to',
        'transfer_date',
        'status',
        'log_user'
    ];

    public function products()
    {
        return $this->hasMany(StockTransferProductsModel::class, 'transfer_id', 'transfer_id');
    }
}
