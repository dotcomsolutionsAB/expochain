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
        'purchase_invoice_no',
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
}
