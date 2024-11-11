<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyProductsModel extends Model
{
    //
    protected $table = 't_assembly_products';

    protected $fillable = [
        'assembly_id',
        'company_id',
        'product_id',
        'product_name',
        'quantity',
        'log_user'
    ];
}
