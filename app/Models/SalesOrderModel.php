<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderModel extends Model
{
    //
    protected $table = 't_sales_order';

    protected $fillable = [
        'company_id',
        'client_id',
        'name',
        'sales_order_no',
        'sales_order_date',
        'ref_no',
        'template',
        'contact_person',
        'status',
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
        return $this->hasMany(SalesOrderProductsModel::class, 'sales_order_id');
    }

    public function addons()
    {
        return $this->hasMany(SalesOrderAddonsModel::class, 'sales_order_id');
    }

    public function get_user()
    {
        return $this->belongsTo(User::class, 'user', 'id'); 
    }

    public function client()
    {
        return $this->belongsTo(ClientsModel::class, 'client_id', 'id');
    }

    public function clientAddress()
    {
        return $this->hasOne(ClientAddressModel::class, 'client_id', 'client_id');
    }


}
