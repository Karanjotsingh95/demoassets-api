<?php

namespace App\Models\EquipmentRequests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentRequestAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_request_id',
        'asset_id',
        'child_asset_available',
        'child_asset_id',
        'is_alternative'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'equipment_request_assets';

    // Grab the actual asset
    public function asset()
    {
        return $this->hasOne('App\Models\Assets\Asset', 'id', 'asset_id');
    }

    // Grab the children
    public function childProducts()
    {
        return $this->hasMany('App\Models\Assets\Asset', 'parent_id', 'asset_id');
    }

    // Grab the child product
    public function child()
    {
        return $this->hasOne('App\Models\Assets\Asset', 'id', 'child_asset_id');
    }
    
}
