<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderTermsModel extends Model
{
    //
    protected $table = 't_purchase_order_terms';

    protected $fillable = ['purchase_order_id', 'company_id', 'name', 'value'];

    public function purchase_order()
    {
        return $this->belongsTo(PurchaseOrderModel::class, 'purchase_order_id','id');
    }
}
