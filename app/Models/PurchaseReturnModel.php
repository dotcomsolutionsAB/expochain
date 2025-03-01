<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnModel extends Model
{
    //
    protected $table = 't_purchase_return';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'name',
        'purchase_return_no',
        'purchase_return_date',
        'purchase_invoice_id',
        'remarks',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'template',
        'gross',
        'round_off'
    ];

    // Defining the relationship with PurchaseReturnProductsModel
    public function products()
    {
        return $this->hasMany(PurchaseReturnProductsModel::class, 'purchase_return_number', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(SuppliersModel::class, 'supplier_id', 'id');
    }

    public function addresses()
    {
        // Here, 'supplier_id' in SupplierAddressModel should match the 'supplier_id' in ClientsModel.
        return $this->hasMany(SupplierAddressModel::class, 'supplier_id', 'supplier_id');
    }
}
