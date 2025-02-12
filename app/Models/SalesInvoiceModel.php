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
        // 'client_contact_id',
        'name',
        // 'address_line_1',
        // 'address_line_2',
        // 'city',
        // 'pincode',
        // 'state',
        // 'country',
        'user',
        'sales_invoice_no',
        'sales_invoice_date',
        'sales_order_id',
        'sales_order_date',
        'template',
        'contact_person',
        'cash',
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
