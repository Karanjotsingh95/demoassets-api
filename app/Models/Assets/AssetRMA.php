<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Localities\Region;

class AssetRMA extends Model
{
    use HasFactory;

    protected $fillable = [
        'link',
        'title',
        'created_by',
        'email',
        'notes',
        'asset_id'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'rma_transactions';

    public function asset()
    {
        return $this->hasOne('App\Models\Assets\Asset', 'id', 'asset_id');
    }

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }

}
