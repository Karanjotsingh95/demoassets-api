<?php

namespace App\Models\EquipmentRequests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'purpose',
        'start_date',
        'end_date',
        'shipping_info',
        'comments',
        'accepted',
        'admin_feedback',
        'status'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'equipment_requests';

    // Grab the requests assets
    public function assets()
    {
        return $this->hasMany('App\Models\EquipmentRequests\EquipmentRequestAsset', 'equipment_request_id', 'id');
    }

    // Grab the user
    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

}
