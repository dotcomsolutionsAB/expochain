<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccess extends Model
{
    protected $table = 't_user_access';

    protected $fillable = [
        'company_id',
        'user_id',
        'module',
        'function',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function company()
    {
        // Update table/key if your CompanyModel uses a different table
        return $this->belongsTo(CompanyModel::class, 'company_id', 'id');
    }

    public function accesses()
    {
        return $this->hasMany(\App\Models\UserAccess::class, 'user_id', 'id');
    }
}