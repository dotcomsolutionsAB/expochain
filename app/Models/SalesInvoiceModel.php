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
        'template',
        'sales_person',
        'commission',
        'cash',
        'user',
        'cgst',
        'sgst',
        'igst',
        'total',
        'gross',
        'round_off'
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

    public function client()
    {
        return $this->belongsTo(ClientsModel::class, 'client_id', 'id');
    }

    public function addresses()
    {
        // Here, 'customer_id' in ClientAddressModel should match the 'customer_id' in ClientsModel.
        return $this->hasMany(ClientAddressModel::class, 'customer_id', 'customer_id');
    }

}
