<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'columns',
        'filters',
        'created_by',
        'auto_report',
        'auto_report_type',
        'auto_report_date'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
    // The mySQL table for this eloquent model.
    protected $table = 'reports';   

}
