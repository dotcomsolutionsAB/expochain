<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceModel extends Model
{
    //
    protected $table = 't_sales_invoice';

    protected $fillable = [
        'company_id',
        'client_id',
        'name',
        'sales_invoice_no',
        'sales_invoice_date',
        'sales_order_id',
        'sales_order_date',
        'template',
        'contact_person',
        'cash',
        'user',
        'cgst',
        'sgst',
        'igst',
        'total',
    ];

    public function products()
    {
        return $this->hasMany(SalesInvoiceProductsModel::class, 'sales_invoice_id');
    }

    public function addons()
    {
        return $this->hasMany(SalesInvoiceAddonsModel::class, 'sales_invoice_id');
    }

    public function get_user()
    {
        return $this->belongsTo(User::class, 'user', 'id'); 
    }

}
