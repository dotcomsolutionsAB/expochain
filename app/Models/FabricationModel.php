<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FabricationModel extends Model
{
    //
    protected $table = 't_fabrications';

    protected $fillable = [
        'company_id',
        'vendor_id',    // Correct spelling is vendor_id (but keep as per your migration)
        'fb_date',
        'invoice_no',
        'remarks',
        'fb_amount'
    ];

    public function products() 
    {
        return $this->hasMany(FabricationProductsModel::class, 'fb_id', 'id');
    }
}
