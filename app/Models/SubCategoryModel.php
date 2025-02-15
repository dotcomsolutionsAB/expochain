<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCategoryModel extends Model
{
    //
    protected $table = 't_sub_category';

    protected $fillable = [
        'serial_number',
        'company_id',
        'category_id',
        'name',
        'logo',
    ];
}
