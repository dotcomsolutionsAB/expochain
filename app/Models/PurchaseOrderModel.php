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
        'purchase_order_id', 
        'purchase_order_date',
        'oa_no', 
        'oa_date', 
        'template',
        'status',
        'user', 
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'gross',
        'round_off'
    ];

    public function products()
    {
        return $this->hasMany(PurchaseOrderProductsModel::class, 'purchase_order_id', 'id');
    }

    public function addons()
    {
        return $this->hasMany(PurchaseOrderAddonsModel::class, 'purchase_order_id', 'id');
    }

    public function terms()
    {
        return $this->hasMany(PurchaseOrderTermsModel::class, 'purchase_order_id', 'id');
    }

    public function get_user()
    {
        return $this->belongsTo(User::class, 'user', 'id'); 
    }

    public function get_template()
    {
        return $this->belongsTo(PdfTemplateModel::class, 'template', 'id');
    }
}
