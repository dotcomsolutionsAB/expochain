<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationTermsModel extends Model
{
    //
    protected $table = 't_quotation_terms';

    protected $fillable = ['quotation_id', 'company_id', 'name', 'value', 'term_master_id'];

    public function quotation()
    {
        return $this->belongsTo(QuotationsModel::class, 'quotation_id', 'id');
    }

    public function termMaster()
    {
        return $this->belongsTo(QuotationTermMasterModel::class, 'term_master_id', 'id');
    }   
}
