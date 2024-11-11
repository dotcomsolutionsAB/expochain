<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderAddonsModel extends Model
{
    //
    protected $table = 't_sales_order_addons';

    protected $fillable = [
       'sales_order_id',
       'company_id',
        'name',
        'amount',
        'tax',
        'hsn',
        'cgst',
        'sgst', 
        'igst',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrderModel::class, 'sales_order_id');
    }


}
