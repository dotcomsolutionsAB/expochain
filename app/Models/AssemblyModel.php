<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyModel extends Model
{
    //
    protected $table = 't_assembly';

    protected $fillable = [
        'assembly_id',
        'product_id',
        'product_name',
        'quantity',
        'log_user'
    ];
}
