<?php

namespace App\Models\CustomerDropOff;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtensionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'start_date',
        'end_date',
        'new_end_date',
        'request_status',
        'comments'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'extension_request';

    // Grab the requests assets
    public function asset()
    {
        return $this->hasOne('App\Models\Assets\Asset', 'id', 'asset_id');
    }

    // Grab the user
    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

}
