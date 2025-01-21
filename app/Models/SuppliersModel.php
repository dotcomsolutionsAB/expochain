<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuppliersModel extends Model
{
    //
    protected $table = 't_suppliers';

    protected $fillable = [
        'supplier_id',
        'company_id',
        'name',
        'gstin',
        'default_contact',
    ];

    // Relationship with contacts
    public function contacts()
    {
        return $this->hasMany(SuppliersContactsModel::class, 'supplier_id', 'supplier_id');
    }

    // Relationship with addresses
    public function addresses()
    {
        return $this->hasMany(SupplierAddressModel::class, 'supplier_id', 'supplier_id');
    }
}
