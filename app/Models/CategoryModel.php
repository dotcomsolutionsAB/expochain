<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryModel extends Model
{
    //
    protected $table = 't_category';

    protected $fillable = [
        'serial_number',
        'company_id',
        'name',
        'logo',
    ];
}
