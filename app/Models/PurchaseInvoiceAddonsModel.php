<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceAddonsModel extends Model
{
    //
    protected $table = 't_purchase_invoice_addons';

    protected $fillable = ['purchase_invoice_id', 'company_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst'];

    public function purchase_invoice()
    {
        return $this->belongsTo(PurchaseInvoiceModel::class, 'purchase_invoice_id','id');
    }
}
