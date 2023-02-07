<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'created_by',
        'emails',
        'type',
        'week',
        'day',
        'time'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
    public function report()
    {
        return $this->hasOne('App\Models\Report', 'id', 'report_id');
    }
    // The mySQL table for this eloquent model.
    protected $table = 'auto_reports';   

}
