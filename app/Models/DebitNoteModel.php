<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebitNoteModel extends Model
{
    //
    protected $table = 't_debit_note';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'name',
        'debit_note_no',
        'debit_note_date',
        'si_no',
        'effective_date',
        'type',
        'remarks',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        //'template',
        'gross',
        'round_off'
    ];

    // Defining the relationship with PurchaseReturnProductsModel
    public function products()
    {
        return $this->hasMany(DebitNoteProductsModel::class, 'debit_note_number', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(SuppliersModel::class, 'supplier_id', 'id');
    }

    public function addresses()
    {
        // Here, 'supplier_id' in SupplierAddressModel should match the 'supplier_id' in ClientsModel.
        return $this->hasMany(SupplierAddressModel::class, 'supplier_id', 'supplier_id');
    }
}
