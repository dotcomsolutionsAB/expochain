<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientContactsModel extends Model
{
    //
    protected $table = 't_client_contacts';

    protected $fillable = [
        'customer_id',
        'company_id',
        'name',
        'designation',
        'mobile',
        'email',
    ];

    // Define the inverse relationship back to the Client
    public function client()
    {
        return $this->belongsTo(ClientsModel::class, 'customer_id', 'customer_id');
    }
}
