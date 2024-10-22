<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyOperationProductsModel extends Model
{
    //
    protected $table = 't_assembly_operations_products';

    protected $fillable = [
        'assembly_operations_id',
        'product_id',
        'product_name',
        'quantity',
        'rate',
        'godown',
        'amount'
    ];
}
