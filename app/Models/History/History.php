<?php

namespace App\Models\History;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;

    protected $fillable = [
        'event',
        'asset_id',
        'changed_from',
        'changed_to',
        'action_by'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'action_by');
    }
    // The mySQL table for this eloquent model.
    protected $table = 'history';   

}
