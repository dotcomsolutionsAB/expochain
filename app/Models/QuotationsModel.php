<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationsModel extends Model
{
    //
    protected $table = 't_quotations';

    protected $fillable = [        
        'company_id',
        'client_id',
        'name',
        'quotation_no',
        'quotation_date',
        'enquiry_no',
        'enquiry_date',
        'template',
        'contact_person',
        'sales_person',
        'status',
        'user',
        'cgst',
        'sgst',
        'igst',
        'total',
        'currency',
        'gross',
        'round_off'
    ];

    public function products()
    {
        return $this->hasMany(QuotationProductsModel::class, 'quotation_id','id');
    }

    public function addons()
    {
        return $this->hasMany(QuotationAddonsModel::class, 'quotation_id', 'id');
    }

    public function terms()
    {
        return $this->hasMany(QuotationTermsModel::class, 'quotation_id', 'id');
    }

    public function get_user()
    {
        return $this->belongsTo(User::class, 'user', 'id'); 
    }

    public function get_template()
    {
        return $this->belongsTo(PdfTemplateModel::class, 'template', 'id');
    }

    public function salesPerson()
    {
        return $this->belongsTo(User::class, 'sales_person', 'id');
    }

}
