<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferProductsModel extends Model
{
    //
    protected $table = 't_stock_transfer_products';

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'company_id',
        'product_name',
        'description',
        'quantity',
        // 'unit',
        // 'status'
    ];

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransferModel::class, 'stock_transfer_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
}
