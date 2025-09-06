<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StateModel extends Model
{
    //
    protected $table = 't_states';

    protected $fillable = ['name', 'country_name'];

    // Define relationship with CountryModel
    public function country()
    {
        return $this->belongsTo(CountryModel::class, 'country_id');
    }
}
