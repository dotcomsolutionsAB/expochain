<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounterModel extends Model
{
    //
    protected $table = 't_counters';

    protected $fillable = ['company_id', 'name', 'type', 'prefix', 'next_number', 'postfix'];
}
