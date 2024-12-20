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
        'address_line_1',
        'address_line_2',
        'city',
        'pincode',
        'state',
        'country',
        'gstin',
    ];

    // One client has many contacts
    public function contact()
    {
        return $this->hasMany(SuppliersContactsModel::class, 'supplier_id', 'supplier_id');
    }
}
