<?php

namespace App\Models\Localities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_site_name',
        'address',
        'phone',
        'email'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'locality_repair_sites';
}
