<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebitNoteProductsModel extends Model
{
    //
    protected $table = 't_debit_note_products';

    protected $fillable = [
        'debit_note_number',
        'company_id',
        'product_id',
        'product_name',
        'description',
        'brand',
        'quantity',
        'unit',
        'price',
        'discount',
        'hsn',
        'tax',
        'cgst',
        'sgst',
        'igst',
    ];

    // Defining the relationship with PurchaseReturnModel (Parent)
    public function purchaseReturn()
    {
        return $this->belongsTo(DebitNoteModel::class, 'debit_note_number', 'id');
    }
}
