<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationProductsModel extends Model
{
    //
    protected $table = 't_quotation_products';

    protected $fillable = ['quotation_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst'];

    public function quotation()
    {
        return $this->belongsTo(QuotationsModel::class, 'quotation_id','id');
    }
}
