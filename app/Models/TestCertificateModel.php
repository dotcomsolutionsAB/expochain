<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestCertificateModel extends Model
{
    //
    protected $table = 't_test_certificate';

    protected $fillable = [
        'client_id',
        'sales_invoice_no',
        'reference_no',
        'tc_date',
        'seller',
        'client_flag',
        'log_user'
    ];

    public function products()
    {
        return $this->hasMany(TestCertificateProductsModel::class, 'tc_id', 'id');
    }
}
