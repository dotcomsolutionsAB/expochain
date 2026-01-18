<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderModel extends Model
{
    //
    protected $table = 't_sales_order';
    
    protected $primaryKey = 'so_id';
    
    public $incrementing = true;

    protected $fillable = [
        'company_id',
        'client_id',
        'name',
        'sales_order_no',
        'sales_order_date',
        'ref_no',
        'template',
        'sales_person',
        'status',
        'user',
        'cgst',
        'sgst',
        'igst',
        'total',
        'gross',
        'round_off'
    ];

    public function products()
    {
        return $this->hasMany(SalesOrderProductsModel::class, 'so_id');
    }

    public function addons()
    {
        return $this->hasMany(SalesOrderAddonsModel::class, 'so_id');
    }

    public function get_user()
    {
        return $this->belongsTo(User::class, 'user', 'id'); 
    }

    public function client()
    {
        return $this->belongsTo(ClientsModel::class, 'client_id', 'id');
    }

    // public function clientAddress()
    // {
    //     return $this->hasOne(ClientAddressModel::class, 'customer_id', 'client_id');
    // }

    // Join Sales Orders with Client Addresses using `customer_id` from `clients`
    public function clientAddress()
    {
        return $this->hasOneThrough(
            ClientAddressModel::class, // Final table
            ClientsModel::class,        // Intermediate table
            'id',                       // Foreign key on Clients that links to SalesOrder (Clients.id = SalesOrder.client_id)
            'customer_id',              // Foreign key on ClientAddress that links to Clients (Clients.customer_id = ClientAddress.customer_id)
            'client_id',                // Local key on SalesOrder
            'customer_id'               // Local key on Clients
        )->selectRaw('t_client_addresses.customer_id as address_customer_id, t_client_addresses.country, t_client_addresses.address_line_1, t_client_addresses.address_line_2, t_client_addresses.city, t_client_addresses.state, t_client_addresses.pincode');
    }

}
