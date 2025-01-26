<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductsModel extends Model
{
    //
    protected $table = 't_products';

    protected $fillable = [
        'serial_number',
        'company_id',
        'name',
        'alias',
        'description',
        'type',
        'group',
        'category',
        'sub_category',
        'cost_price',
        'sale_price',
        'unit',
        'hsn',
        'tax',
    ];
}
