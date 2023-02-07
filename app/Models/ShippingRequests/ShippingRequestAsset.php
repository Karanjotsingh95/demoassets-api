<?php

namespace App\Models\ShippingRequests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRequestAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_request_id',
        'asset_id',
        'damage_report',
        'image_1',
        'image_2',
        'image_3',
        'image_4',
        'image_5'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'shipping_request_assets';

    // Grab the actual asset
    public function asset()
    {
        return $this->hasOne('App\Models\Assets\Asset', 'id', 'asset_id');
    }
}
