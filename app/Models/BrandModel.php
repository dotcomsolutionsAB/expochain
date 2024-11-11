<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandModel extends Model
{
    //
    protected $table = 't_brand';

    protected $fillable = [
        'serial_number',
        'company_id',
        'name',
        'logo',
    ];
}
