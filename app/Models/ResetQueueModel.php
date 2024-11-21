<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetQueueModel extends Model
{
    //
    protected $table = 't_reset_queue';

    protected $fillable = ['company_id','product_id', 'status'];
}
