<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Localities\Region;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_title',
        'mn_number',
        'equipment_id',
        'description',
        'catalog_number',
        'asset_image',
        'functional_procedure',
        'manufacturing_site',
        'repair_site',
        'application_segment',
        'serial_number',
        'status',
        'condition',
        'fw_version',
        'sw_version',
        'last_calibration',
        'calibration_due',
        'purchase_date',
        'assigned_to',
        'region_id',
        'territory_id',
        'last_updated',
        'owner_region_id',
        'last_transaction_date',
        'due_date',
        'created_by',
        'is_unique',
        'parent_id',
        'is_accessory',
        'accessory_mandatory',
        'accessory_qty',
        'list_price',
        'acquisition_date',
        'current_value',
        'send_to',
        'shipping_notes',
        'deliver_by',
        'new_status',
        'depreciation_amount',
        'accessory_check_list'
    ];

    // Protected dates for this eloquent model.
    protected $dates = ['created_at', 'updated_at'];

    // The mySQL table for this eloquent model.
    protected $table = 'assets';


    // Grab the region
    public function region()
    {
        return $this->hasOne('App\Models\Localities\Region', 'id', 'region_id');
    }

    // Get current owner region on asset
    public function ownerRegion()
    {
        return $this->hasOne('App\Models\Localities\Region', 'id', 'owner_region_id');
    }

    // Grab the territory
    public function territory()
    {
        return $this->hasOne('App\Models\Localities\Territory', 'id', 'territory_id');
    }

    // Grab the manufacturing site
    public function manufacturingSite()
    {
        return $this->hasOne('App\Models\Localities\ManufacturingSite', 'id', 'manufacturing_site');
    }

    // Grab the repair site
    public function repairSite()
    {
        return $this->hasOne('App\Models\Localities\RepairSite', 'id', 'repair_site');
    }

    // Grab the assignee
    public function assignee()
    {
        return $this->hasOne('App\Models\User', 'id', 'assigned_to');
    }

    // Grab the children
    public function childProducts()
    {
        return $this->hasMany('App\Models\Assets/Asset', 'id', 'parent_id');
    }

    // Grab the equipment
    public function equipment()
    {
        return $this->hasOne('App\Models\Assets\Equipment', 'id', 'equipment_id');
    }

    // Grab the accessories
    public function accessories()
    {
        return $this->hasMany('App\Models\Assets\Accessory', 'parent_asset_id', 'id');
    }

    // Grab the fields
    public function customFields()
    {
        return $this->hasMany('App\Models\Assets\CustomField', 'asset_id', 'id');
    }

    // Grab the repair sites
    public function repairSites()
    {
        return $this->hasMany('App\Models\Assets\RepSite', 'asset_id', 'id');
    }

    // Grab the receiver
    public function receiver()
    {
        return $this->hasOne('App\Models\User', 'id', 'send_to');
    }
}
