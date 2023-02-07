<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'asset_application_segments';
}
