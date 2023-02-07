<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColumnFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'columns'
      ];
    
      // Protected dates for this eloquent model.
      protected $dates = ['created_at', 'updated_at'];
    
      // The mySQL table for this eloquent model.
      protected $table = 'user_column_filters';
}
