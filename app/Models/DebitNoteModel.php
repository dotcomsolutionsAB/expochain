<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebitNoteModel extends Model
{
    //
    protected $table = 't_debit_note';

    protected $fillable = [
        'supplier_id',
        'name',
        'debit_note_no',
        'debit_note_date',
        'remarks',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'template',
        'status'
    ];
}
