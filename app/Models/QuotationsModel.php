<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationsModel extends Model
{
    //
    protected $table = 't_quotations';

    protected $fillable = [        
        'company_id', 'client_id', 'client_contact_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'quotation_no', 'quotation_date', 'status', 'user', 'enquiry_no', 'enquiry_date', 'sales_person', 'sales_contact', 'sales_email', 'discount', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'contact_person'
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
}
