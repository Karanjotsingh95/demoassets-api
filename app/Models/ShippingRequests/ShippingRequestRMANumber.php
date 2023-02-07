<?php

namespace App\Models\ShippingRequests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRequestRMANumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_request_id',
        'rma_number'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'shipping_request_rma_numbers';
}
