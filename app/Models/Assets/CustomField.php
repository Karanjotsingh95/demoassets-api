<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'field_type',
        'name',
        'default_value'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'asset_custom_fields';


    // Grab the options
    public function options()
    {
        return $this->hasMany('App\Models\Assets\CustomFieldOption', 'custom_field_id', 'id');
    }

    // Grab the asset
    public function asset()
    {
        return $this->hasOne('App\Models\Assets\Asset', 'id', 'asset_id');
    }
}
