<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyOperationProductsModel extends Model
{
    //
    protected $table = 't_assembly_operations_products';

    protected $fillable = [
        'assembly_operations_id',
        'company_id',
        'product_id',
        'product_name',
        'quantity',
        'rate',
        'godown',
        'amount'
    ];

    public function assemblyOperation()
    {
        return $this->belongsTo(AssemblyOperationModel::class, 'assembly_operations_id', 'id');
    }
}
