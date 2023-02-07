<?php

namespace App\Models\CustomerDropOff;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPickUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'assigned_to',
        'customer_drop_off_id',
        'asset_id',
        'pick_up_by',
        'pick_up_date',
        'shipping_address',
        'pick_up_type',
        'comments',
        'status'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'customer_pick_up';

    // Grab the requests assets
    public function assets()
    {
        return $this->hasMany('App\Models\CustomerDropOff\CustomerDropOffAsset', 'customer_drop_off_id', 'customer_drop_off_id');
    }

    // Grab the user
    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

}
