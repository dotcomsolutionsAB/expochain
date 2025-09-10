<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierAddressModel extends Model
{
    //
    protected $table = 't_suppliers_addresses';

    protected $fillable = [
        'company_id', 'type', 'supplier_id', 'country', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode'
    ];

    // Relationship with suppliers
    public function supplier()
    {
        return $this->belongsTo(SuppliersModel::class, 'supplier_id', 'supplier_id');
    }
}
