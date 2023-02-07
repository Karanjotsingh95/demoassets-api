<?php

namespace App\Models\CustomerDropOff;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerDropOffAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_drop_off_id',
        'asset_id'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'customer_drop_off_assets';

    // Grab the actual asset
    public function asset()
    {
        return $this->hasOne('App\Models\Assets\Asset', 'id', 'asset_id');
    }
}
