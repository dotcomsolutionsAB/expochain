<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuppliersContactsModel extends Model
{
    //
    protected $table = 't_suppliers_contacts';

    protected $fillable = [
        'supplier_id',
        'company_id',
        'name',
        'designation',
        'mobile',
        'email',
    ];

    // Define the inverse relationship back to the Suppliers
    public function suppliers()
    {
        return $this->belongsTo(SuppliersModel::class, 'supplier_id', 'supplier_id');
    }
}
