<?php

namespace App\Models\Localities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Territory extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id',
        'territory'
      ];
    
      // Protected dates for this eloquent model.
      protected $dates = ['created_at', 'updated_at'];
    
      // The mySQL table for this eloquent model.
      protected $table = 'locality_territories';

      // Grab the territories region
      public function region() {
        return $this->hasOne('App\Models\Localities\Region', 'id', 'region_id');
      }
}
