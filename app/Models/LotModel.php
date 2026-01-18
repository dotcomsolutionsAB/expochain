<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LotModel extends Model
{
    //
    protected $table = 't_lot';

    protected $fillable = [
        'company_id',
        'name',
        'lr_no',
        'date',
        'shipping_by',
        'freight',
        'invoice',
        'receiving_date',
    ];

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoiceModel::class, 'lot_id', 'id');
    }
}
