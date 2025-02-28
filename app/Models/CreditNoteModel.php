<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNoteModel extends Model
{
    //
    protected $table = 't_credit_note';

    protected $fillable = [
        'company_id',
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
        'gross',
        'round_off'
    ];

    public function products()
    {
        return $this->hasMany(CreditNoteProductsModel::class, 'credit_note_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(ClientsModel::class, 'client_id', 'id');
    }

    public function addresses()
    {
        // Here, 'customer_id' in ClientAddressModel should match the 'customer_id' in ClientsModel.
        return $this->hasMany(ClientAddressModel::class, 'customer_id', 'customer_id');
    }

}
