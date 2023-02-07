<?php

namespace App\Models\Localities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_id',
        'region'
      ];
    
      // Protected dates for this eloquent model.
      protected $dates = ['created_at', 'updated_at'];
    
      // The mySQL table for this eloquent model.
      protected $table = 'locality_regions';

      // Grab the regions territories
      public function territories() {
          return $this->hasMany('App\Models\Localities\Territory', 'region_id', 'id');
      }

      // Grab the regions market
      public function market() {
        return $this->hasOne('App\Models\Localities\Market', 'id', 'market_id');
      }
}
