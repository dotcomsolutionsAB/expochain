<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationProductsModel extends Model
{
    //
    protected $table = 't_quotation_products';

    protected $fillable = ['quotation_id', 'company_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'amount', 'delivery', 'attachment'];

    public function quotation()
    {
        return $this->belongsTo(QuotationsModel::class, 'quotation_id','id');
    }

    public function product()
    {
        return $this->belongsTo(ProductsModel::class, 'product_id');
    }
}
