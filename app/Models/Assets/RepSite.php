<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'repair_site_id'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'asset_repair_sites';

    // Grab the site
    public function site()
    {
        return $this->hasOne('App\Models\Localities\RepairSite', 'id', 'repair_site_id');
    }
}
