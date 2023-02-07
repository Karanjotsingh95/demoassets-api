<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_field_id',
        'text'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'asset_custom_field_options';

    // Grab the cf
    public function field()
    {
        return $this->hasOne('App\Models\Assets\CustomField', 'id', 'custom_field_id');
    }
}
