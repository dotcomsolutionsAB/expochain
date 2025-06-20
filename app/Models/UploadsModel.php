<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadsModel extends Model
{
    //
    protected $table = 't_uploads';

    protected $fillable = [
        'company_id',
        'file_ext',
        'file_url',
        'file_size',
        'file_name'
    ];
}
