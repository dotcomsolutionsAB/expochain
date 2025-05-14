<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerVisitModel extends Model
{
    //
    protected $table = 't_customer_visits';

    protected $fillable = [
        'company_id',
        'date',
        'customer',
        'location',
        'contact_person_name',
        'designation',
        'mobile',
        'email',
        'champion',
        'fenner',
        'details',
        'growth',
        'expense',
        'amount_expense',
        'upload',
        'log_user',
        'log_date'
    ];

    public function logUser()
    {
        return $this->belongsTo(User::class, 'log_user');
    }

}
