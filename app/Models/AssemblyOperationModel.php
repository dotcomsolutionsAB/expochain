<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyOperationModel extends Model
{
    //
    protected $table = 't_assembly_operations';

    protected $fillable = [
        'assembly_operations_id',
        'assembly_operations_date',
        'company_id',
        'type',
        'product_id',
        'product_name',
        'godown',
        'quantity',
        'rate',
        'amount',
        'godown',
        'log_user'
    ];

    public function products()
    {
        return $this->hasMany(AssemblyOperationProductsModel::class, 'assembly_operations_id', 'assembly_operations_id');
    }
}
