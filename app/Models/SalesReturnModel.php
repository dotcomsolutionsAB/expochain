<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnModel extends Model
{
    //
    protected $table = 't_sales_return';

    protected $fillable = [
        'company_id',
        'client_id',
        'name',
        'sales_return_no',
        'sales_return_date',
        'sales_invoice_id',
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

    public function products()
    {
        return $this->hasMany(SalesReturnProductsModel::class, 'sales_return_id', 'id');
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
