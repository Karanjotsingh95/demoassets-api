<?php

namespace App\Models\Localities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Market extends Model
{
    use HasFactory;

    protected $fillable = [
        'market'
      ];
    
      // Protected dates for this eloquent model.
      protected $dates = ['created_at', 'updated_at'];
    
      // The mySQL table for this eloquent model.
      protected $table = 'locality_markets';

      // Grab the markets regions
      public function regions() {
          return $this->hasMany('App\Models\Localities\Region', 'market_id', 'id');
      }
}
