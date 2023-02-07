<?php

namespace App\Models\ShippingRequests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRequestTrackingNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_request_id',
        'tracking_number'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'shipping_request_tracking_numbers';
}
