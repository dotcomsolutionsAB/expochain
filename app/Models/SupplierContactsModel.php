<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierContactsModel extends Model
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

    // Relationship with suppliers
    public function supplier()
    {
        return $this->belongsTo(SuppliersModel::class, 'supplier_id', 'supplier_id');
    }
}
