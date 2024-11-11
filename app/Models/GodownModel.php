<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GodownModel extends Model
{
    //
    protected $table = 't_godown';

    protected $fillable = [
        'company_id',
        'name',
        'address',
        'mobile',
        'email',
    ];
}
