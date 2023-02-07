<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address',
        'primary'
      ];
    
      // Protected dates for this eloquent model.
      protected $dates = ['created_at', 'updated_at'];
    
      // The mySQL table for this eloquent model.
      protected $table = 'user_addresses';
}
