<?php

namespace App\Models\ShippingRequests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'shippers_user_id',
        'ship_to_user_id',
        'ship_to_address',
        'shipment_notes',
        'status',
        'type',
        'selected_rep_site'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'shipping_requests';

    // Grab the requests assets
    public function assets()
    {
        return $this->hasMany('App\Models\ShippingRequests\ShippingRequestAsset', 'shipping_request_id', 'id');
    }

    // Grab the shipper
    public function shipper()
    {
        return $this->hasOne('App\Models\User', 'id', 'shippers_user_id');
    }

    // Grab the receiver
    public function receiver()
    {
        return $this->hasOne('App\Models\User', 'id', 'ship_to_user_id');
    }

    // Grab the tracking numbers
    public function trackingNumbers()
    {
        return $this->hasMany('App\Models\ShippingRequests\ShippingRequestTrackingNumber', 'shipping_request_id', 'id');
    }

    // Grab the additional emails
    public function additionalEmails()
    {
        return $this->hasMany('App\Models\ShippingRequests\ShippingRequestEmail', 'shipping_request_id', 'id');
    }

    // Grab the rep site for RMA type requests
    public function repairSite()
    {
        return $this->hasOne('App\Models\Localities\RepairSite', 'id', 'selected_rep_site');
    }

}
