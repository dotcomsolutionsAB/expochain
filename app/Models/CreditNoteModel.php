<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNoteModel extends Model
{
    //
    protected $table = 't_credit_note';

    protected $fillable = [
        'client_id',
        'name',
        'credit_note_no',
        'credit_note_date',
        'remarks',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'template',
        'status'
    ];

    public function products()
    {
        return $this->hasMany(CreditNoteProductsModel::class, 'credit_note_id', 'id');
    }

}
