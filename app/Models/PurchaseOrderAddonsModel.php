<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderAddonsModel extends Model
{
    //
    protected $table = 't_purchase_order_addons';

    protected $fillable = ['purchase_order_id', 'company_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst'];

    public function purchase_order()
    {
        return $this->belongsTo(PurchaseOrderModel::class, 'purchase_order_id','id');
    }
}
