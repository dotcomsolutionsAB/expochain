<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryModel extends Model
{
    //
    protected $table = 't_countries';

    protected $fillable = ['name'];
}
