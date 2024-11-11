<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyModel extends Model
{
    //
    protected $table = 't_company';

    protected $fillable = ['id', 'name'];

    public function user()
    {
        return $this->hasOne(User::class, 'company_id', 'id');
    }
}
