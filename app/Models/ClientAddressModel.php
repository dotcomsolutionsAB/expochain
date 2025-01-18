<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAddressModel extends Model
{
    //
    protected $table = 't_client_addresses';

    protected $fillable = [
        'company_id', 'type', 'client_id', 'country', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode'
    ];
}
