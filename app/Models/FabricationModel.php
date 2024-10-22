<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FabricationModel extends Model
{
    //
    protected $table = 't_fabrication';

    protected $fillable = [
        'fabrication_date',
        'product_id',
        'product_name',
        'type',
        'quantity',
        'godown',
        'rate',
        'amount',
        'description',
        'log_user'
    ];
}
