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
        'total',
        'gross',
        'round_off',
        'lot_id',
        'po_id'
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

    public function get_template()
    {
        return $this->belongsTo(PdfTemplateModel::class, 'template', 'id');
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
