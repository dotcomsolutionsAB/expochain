<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceModel extends Model
{
    //
    protected $table = 't_sales_invoice';

    protected $fillable = [
       'client_id',
        'client_contact_id',
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'pincode',
        'state',
        'country',
        'sales_invoice_no',
        'sales_invoice_date',
        'sales_order_no',
        'quotation_no',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'template',
        'status',
        'commission',
        'cash',
    ];

    public function products()
    {
        return $this->hasMany(SalesInvoiceProductsModel::class, 'sales_invoice_id');
    }

    public function addons()
    {
        return $this->hasMany(SalesInvoiceAddonsModel::class, 'sales_invoice_id');
    }

}
