<?php

namespace App\Models\HelpGuide;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpGuide extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'link',
        'type'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'help_guides';   

}
