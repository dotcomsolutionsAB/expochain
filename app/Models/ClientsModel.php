<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientsModel extends Model
{
    //
    protected $table = 't_clients';

    protected $fillable = [
        'name',
        'company_id',
        'customer_id',
        'mobile',
        'email',
        'type',
        'category',
        'division',
        'plant',
        'gstin',
        'default_contact',
    ];

    // One client has many contacts
    // public functions constacts
    public function contacts()
    {
        return $this->hasMany(ClientContactsModel::class, 'customer_id', 'customer_id');
    }

    public function addresses()
    {
        return $this->hasMany(ClientAddressModel::class, 'customer_id', 'customer_id');
    }
}
