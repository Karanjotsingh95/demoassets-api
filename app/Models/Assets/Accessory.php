<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accessory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_asset_id',
        'child_asset_id'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'asset_accessories';


    // Grab the accessory
    public function asset()
    {
        return $this->hasOne('App\Models\Assets\Asset', 'id', 'child_asset_id');
    }
}
