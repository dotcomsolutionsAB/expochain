<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderModel extends Model
{
    //
    protected $table = 't_purchase_order';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'pincode',
        'state',
        'country',
        'purchase_order_no',
        'purchase_order_date',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'template',
        'status'
    ];

    public function products()
    {
        return $this->hasMany(PurchaseOrderProductsModel::class, 'purchase_order_number', 'id');
    }
}
