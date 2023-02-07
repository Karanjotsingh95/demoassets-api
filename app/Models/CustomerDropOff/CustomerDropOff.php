<?php

namespace App\Models\CustomerDropOff;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerDropOff extends Model
{
    use HasFactory;

    protected $fillable = [
        'assigned_to',
        'customer_company_name',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'purpose',
        'asset_ids',
        'start_date',
        'end_date',
        'comments'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'customer_drop_off';

    // Grab the requests assets
    public function assets()
    {
        return $this->hasMany('App\Models\CustomerDropOff\CustomerDropOffAsset', 'customer_drop_off_id', 'id');
    }

    // Grab the user
    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

}
