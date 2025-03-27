<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderProductsModel extends Model
{
    //
    protected $table = 't_sales_order_products';

    protected $fillable = [
       'sales_order_id',
        'company_id',
        'product_id',
        'product_name',
        'description',
        'quantity',
        'unit',
        'price',
        'discount',
        'discount_type',
        'hsn',
        'tax',
        'cgst',
        'sgst',
        'igst',
        'amount',
        'channel',
        'sent',
        'short_closed',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrderModel::class, 'sales_order_id');
    }

    public function channel()
    {
        return $this->belongsTo(ChannelModel::class, 'channel', 'id');
    }

    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id');
    }
}
