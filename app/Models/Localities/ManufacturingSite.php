<?php

namespace App\Models\Localities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManufacturingSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'manufacturing_site_name',
        'address',
        'phone',
        'email'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'locality_manufacturing_sites';
}
