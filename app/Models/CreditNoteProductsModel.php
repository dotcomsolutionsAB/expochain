<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNoteProductsModel extends Model
{
    //
    protected $table = 't_credit_note_products';

    protected $fillable = [
        'credit_note_id',
        'product_id',
        'product_name',
        'description',
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
        'godown',
    ];

    public function creditNote()
    {
        return $this->belongsTo(CreditNoteModel::class, 'credit_note_id');
    }

}
