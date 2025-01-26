<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupModel extends Model
{
    //
    protected $table = 't_group';

    protected $fillable = [
        'serial_number',
        'company_id',
        'name',
        'logo',
    ];
}
