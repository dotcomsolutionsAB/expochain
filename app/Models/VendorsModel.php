<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorsModel extends Model
{
    //
    protected $table = 't_vendors';

    protected $fillable = [
        'name',
        'vendor_id',
        'company_id',
        'name',
        'gstin',
        'mobile',
        'email',
    ];
}
