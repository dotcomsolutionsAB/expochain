<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientsModel extends Model
{
    //
    protected $table = 't_clients';

    protected $fillable = [
        'name',
        'customer_id',
        'type',
        'category',
        'division',
        'plant',
        'address_line_1',
        'address_line_2',
        'city',
        'pincode',
        'state',
        'country',
        'gstin',
    ];
}
