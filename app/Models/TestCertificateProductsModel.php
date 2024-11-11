<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestCertificateProductsModel extends Model
{
    //
    protected $table = 't_test_certificate_products';

    protected $fillable = [
        'tc_id',
        'company_id',
        'product_id',
        'product_name',
        'quantity',
        'sales_invoice_no'
    ];
}
