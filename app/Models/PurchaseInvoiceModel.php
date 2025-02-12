<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceModel extends Model
{
    //
    protected $table = 't_purchase_invoice';

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
        'purchase_invoice_no',
        'purchase_invoice_date',
        'purchase_order_no',
        'cgst',
        'sgst',
        'igst',
        'currency',
        'template',
        'status'
    ];

    public function products()
    {
        return $this->hasMany(PurchaseInvoiceProductsModel::class, 'purchase_invoice_number', 'id');
    }

    public function get_user()
    {
        return $this->belongsTo(User::class, 'user', 'id'); 
    }
}
