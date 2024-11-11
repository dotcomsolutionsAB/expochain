<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationsModel extends Model
{
    //
    protected $table = 't_quotations';

    protected $fillable = [        
        'company_id', 'client_id', 'client_contact_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'quotation_no', 'quotation_date', 'enquiry_no', 'enquiry_date', 'sales_person', 'sales_contact', 'sales_email', 'discount', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template'
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
}
