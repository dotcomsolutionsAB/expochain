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
        'client_contact_id',
        'name',
        'address_line_1',
        'address_line_2',
        'city',
        'pincode',
        'state',
        'country',
        'user',
        'sales_order_no',
        'sales_order_date',
        'ref_no',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'template',
        'status',
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

}
