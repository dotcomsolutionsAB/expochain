<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountModel extends Model
{
    //
    protected $table = 't_discount';

    protected $fillable = ['client', 'category', 'sub_category', 'amount'];
}
