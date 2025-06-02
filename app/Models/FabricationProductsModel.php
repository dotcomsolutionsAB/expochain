<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FabricationProductsModel extends Model
{
    //
    protected $table = 't_fabrication_products';

    protected $fillable = [
        'company_id',
        'fb_id',
        'product_id',
        'quantity',
        'rate',
        'amount',
        'godown_id',
        'remarks',
        'type',
        'wastage'
    ];

    public function product() 
    {
        return $this->belongsTo(ProductsModel::class, 'product_id');
    }
}
