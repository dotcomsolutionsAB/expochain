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
        'purchase_invoice_no',
        'purchase_invoice_date',
        'oa_no', 
        'ref_no', 
        'template',
        'user', 
        'cgst',
        'sgst',
        'igst',
        'total'
    ];

    public function products()
    {
        return $this->hasMany(PurchaseInvoiceProductsModel::class, 'purchase_invoice_id', 'id');
    }

    public function get_user()
    {
        return $this->belongsTo(User::class, 'user', 'id'); 
    }

    public function addons()
    {
        return $this->hasMany(PurchaseInvoiceAddonsModel::class, 'purchase_invoice_id', 'id');
    }
}
