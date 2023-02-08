<?php

namespace App\Http\Controllers\Api\Admin\Assets;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Assets\Asset;
use App\Models\Assets\AssetRMA;
use App\Models\Assets\Equipment;
use App\Models\Assets\Accessory;
use App\Models\Assets\CustomField;
use App\Models\Assets\CustomFieldOption;
use App\Models\Assets\RepSite;
use App\Models\Assets\ApplicationSegment;
use Illuminate\Http\Request;
use App\Models\Localities\RepairSite;
use App\Models\Localities\ManufacturingSite;
use App\Models\Localities\Region;
use App\Models\HelpGuide\HelpGuide;
use App\Models\Reports\Report;
use App\Models\Reports\AutoReport;
use App\Models\History\History;
use App\Jobs\SendEmailJob;
use Maatwebsite\Excel\Facades\Excel;
// use App\Imports\ImportUser;
// use App\Exports\ExportUser;

use Storage;
use DB;

class AssetController extends Controller
{
    // Get all assets
    public function getAssets(Request $request)
    {
        // Start an asset query
        $query = Asset::query();

        // Limit to correct asset type
        if($request->search['asset_type'] == 'unique') {
            $query->where('is_unique', 1);
        }elseif($request->search['asset_type'] == 'accessories') {
            $query->where('is_unique', 0)->where('is_accessory', 1);
        }elseif($request->search['asset_type'] == 'all') {
            $query->where('is_unique', 0)->where('is_accessory', 0);
        }else {
            $query->where('is_unique', 0)->where('is_accessory', 0);
        }

        // Do we want to see only assets assigned to a certain user
        if(isset($request->search['assigned_to']) && $request->search['assigned_to'] !== 'null') {
            $query->where('assigned_to', $request->search['assigned_to']);
        }

        // Are we filtering by a date?
        if(isset($request->search['filter_by_date']) && $request->search['filter_by_date'] == 'true') {
            if($request->search['date_range'] == 'created') {
                $query->whereDate('created_at', '>=', date('Y-m-d', strtotime($request->search['start_date'])))->whereDate('created_at', '<=', strtotime($request->search['end_date']));
            }elseif($request->search['date_range'] == 'acquired') {
                $query->whereDate('acquisition_date', '>=', date('Y-m-d', strtotime($request->search['start_date'])))->whereDate('acquisition_date', '<=', strtotime($request->search['end_date']));
            }elseif($request->search['date_range'] == 'last_transaction') {
                $query->whereDate('last_transaction_date', '>=', date('Y-m-d', strtotime($request->search['start_date'])))->whereDate('last_transaction_date', '<=', strtotime($request->search['end_date']));
            }
        }

        // Are we sorting by status?
        if(isset($request->search['status']) && $request->search['status'] !== 'null') {
            // Do where want a where, or a where not... note this is a hotfix so that we can
            // search "where NOT status === "Not Available" to meet a late game ticket requirement.
            if(isset($request->search['status_not'])) {
                $query->whereNotIn('status', $request->search['status']);
            } else {
                $query->where('status', $request->search['status']);
            }
        }

        $qualified_assets = $query->get();
        $qualified_ids = [];
        foreach($qualified_assets as $qa) {
            array_push($qualified_ids, $qa->id);
        }

        // Filter by keywords
        if(isset($request->search['keyword']) && $request->search['keyword'] !== '') {
            $i = 0;
            foreach($request->search['selected_fields'] as $key => $field) {
                if($i == 0) {
                    $query->where($field, 'like', '%' . $request->search['keyword'] . '%');
                }else {
                    $query->orWhere($field, 'like', '%' . $request->search['keyword'] . '%');
                }
                $i++;
            }
        }

        $searched_assets = $query->get();
        $final_searched_assets = [];
        foreach($searched_assets as $sa) {
            foreach($qualified_ids as $key => $id) {
                if($sa->id == $id) {
                    array_push($final_searched_assets, $sa);
                }
            }
        }
        $searched_assets = $final_searched_assets;

        $asset_ids = [];

        // Now search custom field options
        if(isset($request->search['include_cf']) && $request->search['include_cf'] == 'true') {
            // Search Custom Fields
            $options = CustomField::where('default_value', 'like', '%' . $request->search['keyword'] . '%')->with('asset')->get();
            $cf_ids = [];
            foreach($options as $opt) {
                array_push($cf_ids, $opt->asset->id);
            }
            if($request->search['asset_type'] == 'all') {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 0)->where('is_accessory', 0)->get();
            }elseif($request->search['asset_type'] == 'unique') {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 1)->where('is_accessory', 0)->get();
            }else {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 0)->where('is_accessory', 1)->get();
            }
            foreach($assets as $a) {
                array_push($asset_ids, $a->id);
            }
            // Now Search Options
            $options = CustomFieldOption::where('text', 'like', '%' . $request->search['keyword'] . '%')->with('field')->with('field.asset')->get();
            $cf_ids = [];
            foreach($options as $opt) {
                array_push($cf_ids, $opt->field->asset->id);
            }
            if($request->search['asset_type'] == 'all') {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 0)->where('is_accessory', 0)->get();
            }elseif($request->search['asset_type'] == 'unique') {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 1)->where('is_accessory', 0)->get();
            }else {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 0)->where('is_accessory', 1)->get();
            }
            foreach($assets as $a) {
                array_push($asset_ids, $a->id);
            }
        }

        // Now grab all relevant assets
        if($request->search['asset_type'] == 'all') {
            $cf_assets = Asset::whereIn('id', $asset_ids)->where('is_unique', 0)->where('is_accessory', 0)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
        }elseif($request->search['asset_type'] == 'unique') {
            $cf_assets = Asset::whereIn('id', $asset_ids)->where('is_unique', 1)->where('is_accessory', 0)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
        }else {
            $cf_assets = Asset::whereIn('id', $asset_ids)->where('is_unique', 0)->where('is_accessory', 1)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
        }
    
        // Now add all ids to an array
        $asset_ids = [];
        foreach($searched_assets as $a) {
            array_push($asset_ids, $a->id);
        }
        foreach($cf_assets as $a) {
            array_push($asset_ids, $a->id);
        }
        return Asset::whereIn('id', $asset_ids)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
    }

    // Get only parent assets
    public function getParentAssets() {
        return Asset::where('is_unique', 1)->orderBy('asset_title', 'asc')->get();
    }

    // Get mass update assets
    public function getMassUpdateAssets(Request $request)
    {
        return Asset::whereIn('id', $request->assets)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
    }

    public function duplicateAsset(Request $request)
    {
        $asset = $this->getAsset($request->asset_id);
        $newAsset = $asset->replicate();
        $newAsset->asset_title = $newAsset->asset_title . ' (duplicate)';
        $newAsset->mn_number = NULL;
        $newAsset->save();

        // Replicate accessories
        foreach($asset->accessories as $acc) {
            Accessory::create([
                'parent_asset_id' => $newAsset->id,
                'child_asset_id' => $acc->child_asset_id
            ]);
        }

        // Add custom fields
        foreach($asset->customFields as $field) {
            $new_field = CustomField::create([
                'asset_id' => $newAsset->id,
                'field_type' => $field->field_type,
                'name' => $field->name,
                'default_value' => $field->default_value
            ]);
            if($field->options->count() > 0) {
                foreach($field->options as $opt) {
                    CustomFieldOption::create([
                        'custom_field_id' => $new_field->id,
                        'text' => $opt->text
                    ]);
                }
            }
        }

        return $newAsset;
    }

    // Get a single asset
    public function getAsset($id)
    {
        return Asset::where('id', $id)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->first();
    }

    // Create an asset
    public function createAsset(Request $request)
    {
        // See who the user is posting the data
        $user = User::where('id', $request->user()->id)->first();

        if (isset($request->asset['parent_id'])) {
            // Set the parent id
            $parent = $request->asset['parent_id'];
        } else {
            $parent = null;
        }

        if (isset($request->asset['current_value'])) {
            // Set the parent id
            $current_value = $request->asset['current_value'];
        } else {
            $current_value = null;
        }

        $asset = Asset::create([
            'asset_title' => $request->asset['asset_title'],
            'mn_number' => $request->asset['mn_number'],
            'equipment_id' => $request->asset['equipment_id'],
            'description' => $request->asset['description'],
            'catalog_number' => $request->asset['catalog_number'],
            'asset_image' => $request->asset['asset_image'],
            'functional_procedure' => $request->asset['functional_procedure'],
            'manufacturing_site' => $request->asset['manufacturing_site'],
            'repair_site' => $request->asset['repair_site'],
            'application_segment' => $request->asset['application_segment'],
            'serial_number' => $request->asset['serial_number'],
            'status' => $request->asset['status'],
            'condition' => $request->asset['condition'],
            'fw_version' => $request->asset['fw_version'],
            'sw_version' => $request->asset['sw_version'],
            'last_calibration' => date('Y-m-d', strtotime($request->asset['last_calibration'])),
            'calibration_due' => date('Y-m-d', strtotime($request->asset['calibration_due'])),
            'purchase_date' => date('Y-m-d', strtotime($request->asset['purchase_date'])),
            'assigned_to' => $request->asset['assigned_to'],
            'region_id' => $request->asset['region_id'],
            'territory_id' => $request->asset['territory_id'],
            'due_date' => $request->asset['due_date'],
            'created_by' => $user->id,
            'is_unique' => $request->asset['is_unique'],
            'parent_id' => $parent,
            'is_accessory' => $request->asset['is_accessory'],
            'accessory_mandatory' => $request->asset['accessory_mandatory'],
            'accessory_qty' => $request->asset['accessory_qty'],
            'list_price' => $request->asset['list_price'],
            'acquisition_date' => date('Y-m-d', strtotime($request->asset['acquisition_date'])),
            'current_value' => $current_value
        ]);

        // Add Repair Sites
        foreach($request->asset['repair_sites'] as $site) {
            RepSite::create([
                'asset_id' => $asset->id,
                'repair_site_id' => $site['site']['id']
            ]);
        }

        // Add accessories
        foreach($request->asset['accessories'] as $acc) {
            Accessory::create([
                'parent_asset_id' => $asset->id,
                'child_asset_id' => $acc['id']
            ]);
        }

        // Add custom fields
        foreach($request->asset['custom_fields'] as $field) {
            $new_field = CustomField::create([
                'asset_id' => $asset->id,
                'field_type' => $field['field_type'],
                'name' => $field['name'],
                'default_value' => $field['default_value']
            ]);
            if(!empty($field['options'])) {
                foreach($field['options'] as $opt) {
                    CustomFieldOption::create([
                        'custom_field_id' => $new_field->id,
                        'text' => $opt['text']
                    ]);
                }
            }
        }
        
        return $asset;
    }

    // Update an asset
    public function updateAsset($id, Request $request)
    {
        // See who the user is posting the data
        $user = User::where('id', $request->user()->id)->first();

        if (isset($request->asset['parent_id'])) {
            // Set the parent id
            $parent = $request->asset['parent_id'];
        } else {
            $parent = null;
        }

        $asset = Asset::find($id);


        //Asset update history start
        $changed_asset = Asset::where('id',$id)->with('region')->with('repairSites')->with('repairSites.site')->first();
        $updated_asset = $request->asset;

        $changed_from = (object)[]; 
        $changed_to = (object)[];
        foreach($changed_asset->toArray() as $key => $value) {
            if($key=='region_id' && $value!=$updated_asset[$key]){
                $updated_region = Region::where('id', $request->asset['region_id'])->first();
                info($changed_asset);
                if($changed_asset['region']){
                    $changed_from->region = $changed_asset['region']['region'];
                }else{
                    $changed_from->region = 'NA';
                }
                $changed_to->region = $updated_region['region'];
            }else if($key=='manufacturing_site' && $value!=$updated_asset[$key]){
                $updated_msite = ManufacturingSite::where('id', $updated_asset['manufacturing_site'])->first();
                if($changed_asset['manufacturing_site']){
                    $msite = ManufacturingSite::where('id', $value)->first();
                    $changed_from->$key = $msite['manufacturing_site_name'];
                }else{
                    $changed_from->$key = 'NA';
                }
                // $changed_from->$key = $msite['manufacturing_site_name'];
                $changed_to->$key = $updated_msite['manufacturing_site_name'];
            }
            else if($value!=$updated_asset[$key] && $key!="updated_at"){
                $changed_from->$key = $value;
                $changed_to->$key = $updated_asset[$key];
            }
        }
        // info($changed_asset);
        // info($updated_asset);

        if(count((array)$changed_to)>0){
            $history = History::create([
                'asset_id' => $asset['id'],
                'event' => 'Update',
                'changed_from' =>  json_encode($changed_from),
                'changed_to' =>  json_encode($changed_to),
                'action_by' => $request->user()->id
            ]);
        }
        //asset update history end
        $asset->asset_title = $request->asset['asset_title'];
        $asset->accessory_check_list = $request->asset['accessory_check_list'];
        $asset->mn_number = $request->asset['mn_number'];
        $asset->equipment_id = $request->asset['equipment_id'];
        $asset->description = $request->asset['description'];
        $asset->catalog_number = $request->asset['catalog_number'];
        $asset->asset_image = $request->asset['asset_image'];
        $asset->functional_procedure = $request->asset['functional_procedure'];
        $asset->manufacturing_site = $request->asset['manufacturing_site'];
        $asset->repair_site = $request->asset['repair_site'];
        $asset->application_segment = $request->asset['application_segment'];
        $asset->serial_number = $request->asset['serial_number'];
        $asset->status = $request->asset['status'];
        $asset->condition = $request->asset['condition'];
        $asset->fw_version = $request->asset['fw_version'];
        $asset->sw_version = $request->asset['sw_version'];
        $asset->last_calibration = date('Y-m-d', strtotime($request->asset['last_calibration']));
        $asset->calibration_due = date('Y-m-d', strtotime($request->asset['calibration_due']));
        $asset->purchase_date = date('Y-m-d', strtotime($request->asset['purchase_date']));
        $asset->assigned_to = $request->asset['assigned_to'];
        $asset->region_id = $request->asset['region_id'];
        $asset->territory_id = $request->asset['territory_id'];
        $asset->due_date = $request->asset['due_date'];
        $asset->created_by = $user->id;
        $asset->is_unique = $request->asset['is_unique'];
        $asset->parent_id = $parent;
        $asset->is_accessory = $request->asset['is_accessory'];
        $asset->accessory_mandatory = $request->asset['accessory_mandatory'];
        $asset->accessory_qty = $request->asset['accessory_qty'];
        $asset->list_price = $request->asset['list_price'];
        $asset->depreciation_amount = $request->asset['depreciation_amount'];
        $asset->acquisition_date = date('Y-m-d', strtotime($request->asset['acquisition_date']));
        $asset->current_value = $request->asset['current_value'];
        
        $asset->save();

        // Delete all repair Sites
        RepSite::where('asset_id', $asset->id)->delete();

        // Add Repair Sites
        foreach($request->asset['repair_sites'] as $site) {
            RepSite::create([
                'asset_id' => $asset->id,
                'repair_site_id' => $site['site']['id']
            ]);
        }

        // Delete all current accessories
        Accessory::where('parent_asset_id', $asset->id)->delete();

        // Add accessories
        foreach($request->asset['accessories'] as $acc) {
            Accessory::create([
                'parent_asset_id' => $asset->id,
                'child_asset_id' => $acc['asset']['id']
            ]);
        }

        // Delete all current custom fields
        // All fields
        $fields = CustomField::where('asset_id', $asset->id)->get();

        foreach($fields as $f) {
            // Delete options
            CustomFieldOption::where('custom_field_id', $f->id)->delete();
            $f->delete();
        }

        // Add custom fields
        foreach($request->asset['custom_fields'] as $field) {
            $new_field = CustomField::create([
                'asset_id' => $asset->id,
                'field_type' => $field['field_type'],
                'name' => $field['name'],
                'default_value' => $field['default_value']
            ]);
            if(!empty($field['options'])) {
                foreach($field['options'] as $opt) {
                    CustomFieldOption::create([
                        'custom_field_id' => $new_field->id,
                        'text' => $opt['text']
                    ]);
                }
            }
        }

        // Now update all the children
        $children = Asset::where('parent_id', $asset->id)->get();

        foreach($children as $child) {
            if($asset->asset_image) {
                $child->asset_image = $asset->asset_image;
            }
            $child->asset_title = $asset->asset_title;
            $child->description = $asset->description;
            $child->catalog_number = $asset->catalog_number;
            $child->functional_procedure = $asset->functional_procedure;
            $child->list_price = $asset->list_price;
            $child->manufacturing_site = $asset->manufacturing_site;
            $child->application_segment = $asset->application_segment;
            $child->save();

            // Delete all repair Sites
            RepSite::where('asset_id', $child->id)->delete();

            // Add Repair Sites
            foreach($request->asset['repair_sites'] as $site) {
                RepSite::create([
                    'asset_id' => $child->id,
                    'repair_site_id' => $site['site']['id']
                ]);
            }

            // Delete all current accessories
            Accessory::where('parent_asset_id', $child->id)->delete();

            // Add accessories
            foreach($request->asset['accessories'] as $acc) {
                Accessory::create([
                    'parent_asset_id' => $child->id,
                    'child_asset_id' => $acc['asset']['id']
                ]);
            }
        }
        
        return $asset;
    }

    // Delete an asset
    public function deleteAsset($id, Request $request)
    {
        $asset = $this->getAsset($id);

        // Delete the accessories
        foreach($asset->accessories as $acc) {
            $acc->delete();
        }

        // Delete the custom fields and options
        foreach($asset->customFields as $cf) {
            foreach($cf->options as $opt) {
                $opt->delete();
            }
            $cf->delete();
        }
        // Delete the asset
        $asset->delete();
        return $asset;
    }

    // Get all equipment
    public function getEquipment()
    {
        return Equipment::get();
    }

    // Get Application Segments
    public function getApplicationSegments() {
        return ApplicationSegment::orderBy('name', 'asc')->get();
    }

    // Get Application Segment
    public function getApplicationSegment($id) {
        return ApplicationSegment::find($id);
    }

    // Create Application Segment
    public function createApplicationSegment(Request $request) {
        $seg = ApplicationSegment::create([
            'name' => $request->segment['name']
        ]);
        return $seg;
    }

    // Update Application Segment
    public function updateApplicationSegment($id, Request $request) {
        $seg = ApplicationSegment::find($id);
        $seg->name = $request->segment['name'];
        $seg->save();
        return $seg;
    }

    // Delete an Application Segment
    public function deleteApplicationSegment($id) {
        $seg = ApplicationSegment::where('id', $id)->delete();
        return $seg; 
    }

    // Import Assets
    public function importAssets(Request $request) {

        // See who the user is posting the data
        $user = User::where('id', $request->user()->id)->first();

        $data = json_decode($request->assets, true);

        $i = 0;

        $rows = count($data[0]['data']);

        if($request->type == 'accessory') {
            while($i <= $rows) {
                // Seeing if all the data exists for each column
                $asset_image = '';
                if(isset($data[0]['data'][$i])) {
                    $asset_image = $data[0]['data'][$i];
                }
                $asset_title = '';
                if(isset($data[1]['data'][$i])) {
                    $asset_title = $data[1]['data'][$i];
                }
                $catalog_number = '';
                if(isset($data[2]['data'][$i])) {
                    $catalog_number = $data[2]['data'][$i];
                }
                $accessory_qty = 1;
                if(isset($data[3]['data'][$i])) {
                    $accessory_qty = $data[3]['data'][$i];
                }
                $accessory_mandatory = 0;
                if(isset($data[4]['data'][$i])) {
                    $accessory_mandatory = $data[4]['data'][$i];
                }

                // Add the accessory
                if($asset_title) {
                    // Use vars above to create new assets
                    $asset = Asset::create([
                        'asset_image' => $asset_image,
                        'asset_title' => $asset_title,
                        'catalog_number' => $catalog_number,
                        'created_by' => $user->id,
                        'is_unique' => 0,
                        'is_accessory' => 1,
                        'accessory_mandatory' => $accessory_mandatory,
                        'accessory_qty' => $accessory_qty
                    ]);
                }

                $i++;
            }
        }elseif($request->type == 'unique') {
            while($i <= $rows) {
                // Seeing if all the data exists for each column
                $asset_image = '';
                if(isset($data[0]['data'][$i])) {
                    $asset_image = $data[0]['data'][$i];
                }
                $asset_title = '';
                if(isset($data[1]['data'][$i])) {
                    $asset_title = $data[1]['data'][$i];
                }
                $description = '';
                if(isset($data[2]['data'][$i])) {
                    $description = $data[2]['data'][$i];
                }
                $catalog_number = '';
                if(isset($data[3]['data'][$i])) {
                    $catalog_number = $data[3]['data'][$i];
                }
                $functional_procedure = '';
                if(isset($data[4]['data'][$i])) {
                    $functional_procedure = $data[4]['data'][$i];
                }
                $list_price = '';
                if(isset($data[5]['data'][$i])) {
                    $list_price = $data[5]['data'][$i];
                }

                // Add the unique asset
                if($asset_title) {
                    // Use vars above to create new assets
                    $asset = Asset::create([
                        'asset_image' => $asset_image,
                        'asset_title' => $asset_title,
                        'description' => $description,
                        'catalog_number' => $catalog_number,
                        'functional_procedure' => $functional_procedure,
                        'mn_number' => uniqid(),
                        'list_price' => $list_price,
                        'created_by' => $user->id,
                        'is_unique' => 1,
                        'is_accessory' => 0
                    ]);
                }

                // Add the application segment
                if(isset($data[7]['data'][$i])) {
                    if(ApplicationSegment::where('name', 'LIKE', '%'.$data[7]['data'][$i].'%')->count() > 0) {
                        $appSeg = ApplicationSegment::where('name', 'LIKE', '%'.$data[7]['data'][$i].'%')->first();
                        $asset->application_segment = $appSeg->name;
                        $asset->save();
                    }
                }

                // Add the manufacturing site
                if(isset($data[6]['data'][$i])) {
                    if(ManufacturingSite::where('manufacturing_site_name', 'LIKE', '%'.$data[6]['data'][$i].'%')->count() > 0) {
                        $mSite = ManufacturingSite::where('manufacturing_site_name', 'LIKE', '%'.$data[6]['data'][$i].'%')->first();
                        $asset->manufacturing_site = $mSite->id;
                        $asset->save();
                    }
                }

                // Now add repair sites
                if(isset($data[8]['data'][$i])) {
                    $repair_sites = explode(",", $data[8]['data'][$i]);
                    foreach($repair_sites as $key => $value) {
                        if(RepairSite::where('repair_site_name', 'LIKE', '%'.$value.'%')->count() > 0) {
                            $repair_site = RepairSite::where('repair_site_name', 'LIKE', '%'.$value.'%')->first();
                            RepSite::create([
                                'asset_id' => $asset->id,
                                'repair_site_id' => $repair_site->id
                            ]);
                        }
                    }
                }

                // Now add asset accessory ids
                if(isset($data[9]['data'][$i])) {
                    $asset_accessory_titles = explode(",", $data[9]['data'][$i]);
                    foreach($asset_accessory_titles as $key => $value) {
                        if(Asset::where('is_accessory', 1)->where('asset_title', 'LIKE', "%{$value}%")->count() > 0) {
                            $acc = Asset::where('is_accessory', 1)->where('asset_title', 'LIKE', "%{$value}%")->first();
                            Accessory::create([
                                'parent_asset_id' => $asset->id,
                                'child_asset_id' => $acc->id
                            ]);
                        }
                    }

                }

                $i++;
            }
        }else {
            // Standard Assets
            while($i <= $rows) {
                // Seeing if all the data exists for each column
                $asset_image = '';
                if(isset($data[0]['data'][$i])) {
                    $asset_image = $data[0]['data'][$i];
                }
                $asset_title = '';
                if(isset($data[1]['data'][$i])) {
                    $asset_title = $data[1]['data'][$i];
                }
                $mn_number = '';
                if(isset($data[2]['data'][$i])) {
                    $mn_number = $data[2]['data'][$i];
                }
                $description = '';
                if(isset($data[3]['data'][$i])) {
                    $description = $data[3]['data'][$i];
                }
                $catalog_number = '';
                if(isset($data[4]['data'][$i])) {
                    $catalog_number = $data[4]['data'][$i];
                }
                $serial_number = '';
                if(isset($data[5]['data'][$i])) {
                    $serial_number = $data[5]['data'][$i];
                }
                $status = '';
                if(isset($data[6]['data'][$i])) {
                    $status = $data[6]['data'][$i];
                }
                $condition = '';
                if(isset($data[7]['data'][$i])) {
                    $condition = $data[7]['data'][$i];
                }
                $purchase_date = '';
                if(isset($data[9]['data'][$i])) {
                    $purchase_date = date('Y-m-d', strtotime($data[9]['data'][$i]));
                }
                $acquisition_date = '';
                if(isset($data[10]['data'][$i])) {
                    $acquisition_date = date('Y-m-d', strtotime($data[10]['data'][$i]));
                }
                $list_price = '';
                if(isset($data[13]['data'][$i])) {
                    $list_price = $data[13]['data'][$i];
                }
                $current_value = '';
                if(isset($data[14]['data'][$i])) {
                    $current_value = $data[14]['data'][$i];
                }
                $firmware_version = '';
                if(isset($data[15]['data'][$i])) {
                    $firmware_version = $data[15]['data'][$i];
                }
                $software_version = '';
                if(isset($data[16]['data'][$i])) {
                    $software_version = $data[16]['data'][$i];
                }
                $last_calibration = '';
                if(isset($data[17]['data'][$i])) {
                    $last_calibration = $data[17]['data'][$i];
                }
                $calibration_due = '';
                if(isset($data[18]['data'][$i])) {
                    $calibration_due = $data[18]['data'][$i];
                }
                $due_date = '';
                if(isset($data[19]['data'][$i])) {
                    $due_date = date('Y-m-d', strtotime($data[19]['data'][$i]));
                }

                $region_id = NULL;
                if(isset($data[11]['data'][$i])) {
                    if(Region::where('region', 'LIKE', "{$data[11]['data'][$i]}")->count() > 0){
                        $region = Region::where('region', 'LIKE',"{$data[11]['data'][$i]}")->first();
                        $region_id = $region->id;
                    }
                }

                $asset = [];
                
                // Check if the mn number exists
                if(Asset::where('mn_number', $mn_number)->count() > 0) {
                    // The asset exists, just update it
                    $asset = Asset::where('mn_number', $mn_number)->first();
                    // if($asset) {
                    //     $asset->asset_image = $asset_image;
                    //     $asset->asset_title = $asset_title;
                    //     $asset->mn_number = $mn_number;
                    //     $asset->description = $description;
                    //     $asset->catalog_number = $catalog_number;
                    //     $asset->serial_number = $serial_number;
                    //     $asset->list_price = $list_price;
                    //     $asset->current_value = $current_value;
                    //     $asset->status = $status;
                    //     $asset->condition = $condition;
                    //     $asset->fw_version = $firmware_version;
                    //     $asset->sw_version = $software_version;
                    //     $asset->last_calibration = $last_calibration;
                    //     $asset->calibration_due = $calibration_due;
                    //     $asset->purchase_date = $purchase_date;
                    //     $asset->due_date = $due_date;
                    //     $asset->acquisition_date = $acquisition_date;
                    //     $asset->created_by = $user->id;
                    //     $asset->save();
                    // }
                } else {
                    // Add the standard asset
                    if($asset_title) {
                        // Use vars above to create new assets
                        $asset = Asset::create([
                            'asset_image' => $asset_image,
                            'asset_title' => $asset_title,
                            'mn_number' => $mn_number,
                            'description' => $description,
                            'catalog_number' => $catalog_number,
                            'serial_number' => $serial_number,
                            'list_price' => $list_price,
                            'current_value' => $current_value,
                            'status' => $status,
                            'region_id' => $region_id,
                            'condition' => $condition,
                            'fw_version' => $firmware_version,
                            'sw_version' => $software_version,
                            'last_calibration' => $last_calibration,
                            'calibration_due' => $calibration_due,
                            'purchase_date' => $purchase_date,
                            'due_date' => $due_date,
                            'acquisition_date' => $acquisition_date,
                            'created_by' => $user->id,
                            'is_unique' => 0,
                            'is_accessory' => 0
                        ]);
                    }
                }

                // Assign to user
                if(isset($data[8]['data'][$i])) {
                    if(User::where(DB::raw('CONCAT_WS(" ", first_name, last_name)'), 'like', $data[8]['data'][$i])->count() > 0) {
                        $u = User::where(DB::raw('CONCAT_WS(" ", first_name, last_name)'), 'like', $data[8]['data'][$i])->first();
                        $asset->assigned_to = $u->id;
                        $asset->save();
                    }
                }

                // Add the parent id
                if(isset($data[12]['data'][$i])) {
                    if(Asset::where('is_unique', 1)->where('asset_title', 'LIKE', "%{$data[12]['data'][$i]}%")->count() > 0) {
                        $a = Asset::where('is_unique', 1)->where('asset_title', 'LIKE', "%{$data[12]['data'][$i]}%")->with('accessories')->with('accessories.asset')->with('repairSites')->first();
                        if($a) {
                            // Copy certain info from parent
                            $asset->parent_id = $a->id;
                            $asset->application_segment = $a->application_segment;
                            $asset->manufacturing_site = $a->manufacturing_site;
                            // $asset->region_id = $a->region_id;
                            $asset->list_price = $a->list_price;
                            $asset->save();

                            // Add all the parent assets accessories to this one
                            foreach($a->accessories as $acc) {
                                Accessory::create([
                                    'parent_asset_id' => $asset->id,
                                    'child_asset_id' => $acc->asset->id
                                ]);
                            }

                            // Add repair sites from parent
                            foreach($a->repairSites as $rs) {
                                RepSite::create([
                                    'asset_id' => $asset->id,
                                    'repair_site_id' => $rs->repair_site_id
                                ]);
                            }
                        }
                    }
                }
                
                $i++;
            }
            // while($i <= $rows) {
            //     // Seeing if all the data exists for each column
            //     $asset_image = '';
            //     if(isset($data[0]['data'][$i])) {
            //         $asset_image = $data[0]['data'][$i];
            //     }
            //     $asset_title = '';
            //     if(isset($data[1]['data'][$i])) {
            //         $asset_title = $data[1]['data'][$i];
            //     }
            //     $mn_number = '';
            //     if(isset($data[2]['data'][$i])) {
            //         $mn_number = $data[2]['data'][$i];
            //     }
            //     $description = '';
            //     if(isset($data[3]['data'][$i])) {
            //         $description = $data[3]['data'][$i];
            //     }
            //     $catalog_number = '';
            //     if(isset($data[4]['data'][$i])) {
            //         $catalog_number = $data[4]['data'][$i];
            //     }
            //     $serial_number = '';
            //     if(isset($data[5]['data'][$i])) {
            //         $serial_number = $data[5]['data'][$i];
            //     }
            //     $list_price = '';
            //     if(isset($data[6]['data'][$i])) {
            //         $list_price = $data[6]['data'][$i];
            //     }
            //     $current_value = '';
            //     if(isset($data[7]['data'][$i])) {
            //         $current_value = $data[7]['data'][$i];
            //     }
            //     $status = '';
            //     if(isset($data[8]['data'][$i])) {
            //         $status = $data[8]['data'][$i];
            //     }
            //     $condition = '';
            //     if(isset($data[9]['data'][$i])) {
            //         $condition = $data[9]['data'][$i];
            //     }
            //     $firmware_version = '';
            //     if(isset($data[11]['data'][$i])) {
            //         $region_id = $data[11]['data'][$i];
            //     }
            //     $software_version = '';
            //     if(isset($data[12]['data'][$i])) {
            //         $software_version = $data[12]['data'][$i];
            //     }
            //     $last_calibration = '';
            //     if(isset($data[13]['data'][$i])) {
            //         $last_calibration = $data[13]['data'][$i];
            //     }
            //     $calibration_due = '';
            //     if(isset($data[14]['data'][$i])) {
            //         $calibration_due = $data[14]['data'][$i];
            //     }
            //     $purchase_date = '';
            //     if(isset($data[15]['data'][$i])) {
            //         $purchase_date = date('Y-m-d', strtotime($data[15]['data'][$i]));
            //     }
            //     $due_date = '';
            //     if(isset($data[16]['data'][$i])) {
            //         $due_date = date('Y-m-d', strtotime($data[16]['data'][$i]));
            //     }
            //     $acquisition_date = '';
            //     if(isset($data[17]['data'][$i])) {
            //         $acquisition_date = date('Y-m-d', strtotime($data[17]['data'][$i]));
            //     }

            //     $asset = [];

            //     // Check if the mn number exists
            //     if(Asset::where('mn_number', $mn_number)->count() > 0) {
            //         // The asset exists, just update it
            //         $asset = Asset::where('mn_number', $mn_number)->first();
            //         if($asset) {
            //             $asset->asset_image = $asset_image;
            //             $asset->asset_title = $asset_title;
            //             $asset->mn_number = $mn_number;
            //             $asset->description = $description;
            //             $asset->catalog_number = $catalog_number;
            //             $asset->serial_number = $serial_number;
            //             $asset->list_price = $list_price;
            //             $asset->current_value = $current_value;
            //             $asset->status = $status;
            //             $asset->condition = $condition;
            //             $asset->fw_version = $firmware_version;
            //             $asset->sw_version = $software_version;
            //             $asset->last_calibration = $last_calibration;
            //             $asset->calibration_due = $calibration_due;
            //             $asset->purchase_date = $purchase_date;
            //             $asset->due_date = $due_date;
            //             $asset->acquisition_date = $acquisition_date;
            //             $asset->created_by = $user->id;
            //             $asset->save();
            //         }
            //     } else {
            //         // Add the standard asset
            //         if($asset_title) {
            //             // Use vars above to create new assets
            //             $asset = Asset::create([
            //                 'asset_image' => $asset_image,
            //                 'asset_title' => $asset_title,
            //                 'mn_number' => $mn_number,
            //                 'description' => $description,
            //                 'catalog_number' => $catalog_number,
            //                 'serial_number' => $serial_number,
            //                 'list_price' => $list_price,
            //                 'current_value' => $current_value,
            //                 'status' => $status,
            //                 'condition' => $condition,
            //                 'fw_version' => $firmware_version,
            //                 'sw_version' => $software_version,
            //                 'last_calibration' => $last_calibration,
            //                 'calibration_due' => $calibration_due,
            //                 'purchase_date' => $purchase_date,
            //                 'due_date' => $due_date,
            //                 'acquisition_date' => $acquisition_date,
            //                 'created_by' => $user->id,
            //                 'is_unique' => 0,
            //                 'is_accessory' => 0
            //             ]);
            //         }
            //     }

            //     // Assign to user
            //     if(isset($data[10]['data'][$i])) {
            //         if(User::where(DB::raw('CONCAT_WS(" ", first_name, last_name)'), 'like', $data[10]['data'][$i])->count() > 0) {
            //             $u = User::where(DB::raw('CONCAT_WS(" ", first_name, last_name)'), 'like', $data[10]['data'][$i])->first();
            //             $asset->assigned_to = $u->id;
            //             $asset->save();
            //         }
            //     }

            //     // Add the parent id
            //     if(isset($data[19]['data'][$i])) {
            //         if(Asset::where('is_unique', 1)->where('asset_title', 'LIKE', "%{$data[19]['data'][$i]}%")->count() > 0) {
            //             $a = Asset::where('is_unique', 1)->where('asset_title', 'LIKE', "%{$data[19]['data'][$i]}%")->with('accessories')->with('accessories.asset')->with('repairSites')->first();
            //             if($a) {
            //                 // Copy certain info from parent
            //                 $asset->parent_id = $a->id;
            //                 $asset->application_segment = $a->application_segment;
            //                 $asset->manufacturing_site = $a->manufacturing_site;
            //                 $asset->region_id = $a->region_id;
            //                 $asset->save();

            //                 // Add all the parent assets accessories to this one
            //                 foreach($a->accessories as $acc) {
            //                     Accessory::create([
            //                         'parent_asset_id' => $asset->id,
            //                         'child_asset_id' => $acc->asset->id
            //                     ]);
            //                 }

            //                 // Add repair sites from parent
            //                 foreach($a->repairSites as $rs) {
            //                     RepSite::create([
            //                         'asset_id' => $asset->id,
            //                         'repair_site_id' => $rs->repair_site_id
            //                     ]);
            //                 }
            //             }
            //         }
            //     }
                
            //     $i++;
            // }
        }
        return 'success';
    }

    // Import Accessories
    public function importAccessories(Request $request) {

        // See who the user is posting the data
        $user = User::where('id', $request->user()->id)->first();

        $data = json_decode($request->assets, true);

        $i = 0;

        $rows = count($data[0]['data']);

        // Seeing if all the data exists for each column
        $asset_title = '';
        if(isset($data[0]['data'][0])) {
            $asset_title = $data[0]['data'][0];
        }
        $asset_image = '';
        if(isset($data[1]['data'][$i])) {
            $asset_image = $data[1]['data'][0];
        }
        $catalog_number = '';
        if(isset($data[2]['data'][$i])) {
            $catalog_number = $data[2]['data'][0];
        }
        $accessory_quantity = 0;
        if(isset($data[3]['data'][$i])) {
            $accessory_quantity = $data[3]['data'][0];
        }
        $accessory_mandatory = 0;
        if(isset($data[4]['data'][$i])) {
            $accessory_mandatory = $data[4]['data'][0];
        }

        while($i <= $rows) {
            // Seeing if all the data exists for each column
            $asset_title = '';
            if(isset($data[0]['data'][$i])) {
                $asset_title = $data[0]['data'][$i];
            }
            $asset_image = '';
            if(isset($data[1]['data'][$i])) {
                $asset_image = $data[1]['data'][$i];
            }
            $catalog_number = '';
            if(isset($data[2]['data'][$i])) {
                $catalog_number = $data[2]['data'][$i];
            }
            $accessory_quantity = 0;
            if(isset($data[3]['data'][$i])) {
                $accessory_quantity = $data[3]['data'][$i];
            }
            $accessory_mandatory = 0;
            if(isset($data[4]['data'][$i])) {
                $accessory_mandatory = $data[4]['data'][$i];
            }

            // checking validation
            if($asset_title && $asset_image && $catalog_number) {
                
                // Use vars above to create new assets
                $asset = Asset::create([
                    'asset_title' => $asset_title,
                    'asset_image' => $asset_image,
                    'catalog_number' => $catalog_number,
                    'accessory_quantity' => $accessory_quantity,
                    'accessory_mandatory' => $accessory_mandatory,
                    'is_unique' => 0,
                    'is_accessory' => 1,
                    'current_value' => 0.00,
                    'created_by' => $user->id
                ]);
            }

            $i++;
        }
        return 'success';
    }

    // Save Mass Updates
    public function saveMassUpdate(Request $request) {
        // Grab the assets being updated
        $assets = Asset::whereIn('id', $request->assets)->get();

        // Update each asset
        foreach($assets as $asset) {
            foreach($request->fields as $key => $value) {
                if($value == 'repair_site') {
                    // Delete its repair sites
                    RepSite::where('asset_id', $asset->id)->delete();
                    foreach($request->new_asset['repair_sites'] as $site) {
                        // Add Repair Sites
                        RepSite::create([
                            'asset_id' => $asset->id,
                            'repair_site_id' => $site['site']['id']
                        ]);
                    }  
                }else {
                    $asset->{$value} = $request->new_asset[$value];
                }
            }; 
            // Save asset
            $asset->save();
        }

        return 'Success';
    }

    // check asset title
    public function checkTitle(Request $request) {
        $titleExists = 0;
        if(Asset::where('asset_title', $request->title)->count() > 0) {
            $titleExists = 1;
        }
        return $titleExists;
    }

    // check mn number
    public function checkMnNumber(Request $request) {
        $mnExists = 0;

        if(Asset::where('mn_number', $request->mn_number)->count() > 0) {
            $mnExists = 1;
        }

        $serialExists = 0;

        if($request->id != null){
            $asset = Asset::where('serial_number', $request->serial_number)->first();
            if($request->id && $request->id != $asset->id){
                $serialExists = 1;
            }
        }else if(Asset::where('serial_number', $request->serial_number)->count() > 0) {
            $serialExists = 1;
        }

        $returnVal = 0;
        if($mnExists == 1 || $serialExists == 1) {
            $returnVal = 1;
        }
        return $returnVal;
    }

    // Get shippable assets
    public function getShippableAssets(Request $request) {
        // See who the user is posting the data
        $user = User::where('id', $request->user()->id)->first();
        // Grab all this users shippable assets
        $my_assets = Asset::whereNotNull('send_to')->whereNotNull('deliver_by')->where('status', '!=', 'In Transit')->where('assigned_to', $user->id)->with('receiver')->get();
        $my_asset_ids = [];
        foreach($my_assets as $asset) {
            $my_asset_ids[] = $asset->id;
        }
        $available_assets = Asset::where('is_unique', 0)->where('is_accessory', 0)->where('status', 'available')->with('receiver')->get();
        $available_asset_ids = [];
        foreach($available_assets as $asset) {
            $available_asset_ids[] = $asset->id;
        }
        $asset_ids = array_merge($my_asset_ids, $available_asset_ids);
        // Get all the assets together
        $assets = Asset::whereIn('id', $asset_ids)->with('receiver')->with('assignee')->with('assignee.primaryAddress')->with('accessories')->with('accessories.asset')->get();
        return $assets;
    }

    public function getReceivableAssets(Request $request) {
        // See who the user is posting the data
        $user = User::where('id', $request->user()->id)->first();
        // Grab all this users shippable assets
        $asset_ids = $request->asset_ids;
        $my_assets = Asset::whereIn('id', $asset_ids)->whereNotNull('send_to')->with('receiver')->with('assignee')->with('assignee.primaryAddress')->where('send_to', $user->id)->with('receiver')->with('accessories')->with('accessories.asset')->get();
        
        return $my_assets;
    }

    // public function getMyEquipment(Request $request) {
    //     // See who the user is posting the data
    //     $user = User::where('id', $request->user()->id)->first();
    //     $my_assets = Asset::where('assigned_to', $user->id)->with('receiver')->with('assignee')->with('assignee.primaryAddress')->with('receiver')->with('accessories')->with('accessories.asset')->get();
    //     return $my_assets;
    // }

    public function getMyEquipment(Request $request) {
        // Start an asset query
        $query = Asset::query();

        $query->where('is_unique', 0)->where('is_accessory', 0);

        // Do we want to see only assets assigned to a certain user


        $qualified_assets = $query->get();
        $qualified_ids = [];
        foreach($qualified_assets as $qa) {
            array_push($qualified_ids, $qa->id);
        }

        // Filter by keywords
        if(isset($request->search['keyword']) && $request->search['keyword'] !== '') {
            $i = 0;
            foreach($request->search['selected_fields'] as $key => $field) {
                if($i == 0) {
                    $query->where($field, 'like', '%' . $request->search['keyword'] . '%');
                }else {
                    $query->orWhere($field, 'like', '%' . $request->search['keyword'] . '%');
                }
                $i++;
            }
        }

        $searched_assets = $query->get();
        $final_searched_assets = [];
        foreach($searched_assets as $sa) {
            foreach($qualified_ids as $key => $id) {
                if($sa->id == $id) {
                    array_push($final_searched_assets, $sa);
                }
            }
        }
        $searched_assets = $final_searched_assets;

        $asset_ids = [];

        $cf_assets = Asset::whereIn('id', $asset_ids)->where('is_unique', 0)->where('is_accessory', 0)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();

        // Now add all ids to an array
        $asset_ids = [];
        foreach($searched_assets as $a) {
            array_push($asset_ids, $a->id);
        }
        foreach($cf_assets as $a) {
            array_push($asset_ids, $a->id);
        }
        $asset_ids = array_unique($asset_ids);
        $my_equipment = [];
        $in_transit_assets = [];
        $assigned_assets = Asset::whereIn('id', $asset_ids)->where('assigned_to',$request->user()->id)->where('status','!=','In Transit')->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
        // info($assigned_assets);
        if($request->search['in_transit'] == 1) {
            $in_transit_from = Asset::whereIn('id', $asset_ids)->where('assigned_to',$request->user()->id)->where('status', 'In Transit')->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
            $in_transit_to = Asset::whereIn('id', $asset_ids)->where('send_to',$request->user()->id)->where('status', 'In Transit')->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('ownerRegion')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
            array_push($in_transit_assets, ...$in_transit_from, ...$in_transit_to );
        }
        // info($assigned_assets);
        array_push($my_equipment, ...$assigned_assets, ...$in_transit_assets);
        // foreach($my_equipment ad $a){
            
        // }

        return $my_equipment;

    }

    public function getSelectedAssets(Request $request){
        $assets = [];
        $assets = Asset::whereIn('id',$request['data'])->with('assignee')->get();
        // foreach($request['data'] as $id){
        //     $asset = Asset::whereIn('id',$id)->with('assignee')->get();
        //     array_push($assets,$asset);
        // }
        return $assets;

    }

    public function createHelpGuide(Request $request){
        $guide = HelpGuide::create([
            'title' => $request->data['title'],
            'link' => $request->data['link'],
            'type' => $request->data['type']
        ]);

        $g = HelpGuide::find($guide['id']);
        return $g;
    }

    public function getHelpGuides(Request $request){
        $guides = HelpGuide::get();
        return $guides;
    }

    public function deleteHelpGuide(Request $request){
        $guide = HelpGuide::where('id', $request['id'])->delete();
        $guides = HelpGuide::get();
        return $guides;
    }

    public function getRMATransactions(Request $request){
        $r = AssetRMA::where('asset_id', $request->id)->with('user')->with('asset')->get();
        return $r;
    }

    public function createRMATransaction(Request $request){
        $user = User::where('id', $request->user()->id)->first();
        $rma = AssetRMA::create([
            'title' => $request->data['title'],
            'link' => $request->data['link'],
            'created_by' => $user->id,
            'email' => $request->data['email'],
            'notes' => $request->data['notes'],
            'asset_id' => $request->data['asset_id']
        ]);
        $r = AssetRMA::with('user')->with('asset')->get();

        //Asset update history start
        $changed_from = (object)[]; 
        $changed_to = (object)[
            'title' => $request->data['title'],
            'link' => $request->data['link'],
            'email' => $request->data['email'],
            'notes' => $request->data['notes'],
        ];

        if(count((array)$changed_to)>0){
            $history = History::create([
                'asset_id' => $request->data['asset_id'],
                'event' => 'Add Transaction',
                'changed_from' =>  json_encode($changed_from),
                'changed_to' =>  json_encode($changed_to),
                'action_by' => $request->user()->id
            ]);
        }
        //end history


        $emails = [];
        array_push($emails, $user->email, $request->data['email']);

        $tbody = '<p> Download Pdf: '.$request->data['link'].'</p><p>Notes: '.$request->data['notes'].'</p>';

        foreach($emails as $key => $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'New transaction created',
                'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).' has created a new RMA transaction.</p>'.$tbody,
                'emailTo' => $email,
                'subject' => 'New Transaction Created',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }
        return $r;
    }

    public function deleteTransaction(Request $request){
        $r = AssetRMA::where('id', $request->id)->delete();
        return 'Success';
    }

    public function getReports(Request $request){
        $user = User::where('id', $request->user()->id)->first();
        $reports = Report::where('created_by', $user->id)->get();
        return $reports;
    }

    public function deleteReport(Request $request){
        $report = Report::where('id', $request->id)->delete();
        $auto_report = AutoReport::where('report_id', $request->id)->delete();
        return "Success";
    }

    public function getReport(Request $request){
        $report = Report::find($request->id);
        return $report;
    }

    public function deleteAutoReport(Request $request){
        $auto_report = AutoReport::where('report_id', $request->id)->delete();
        $report = Report::find($request->id);
        $report['auto_report'] = 0;
        $report->save();

        return "Success";
    }

    public function setAutoReport(Request $request){
        $report = Report::where('id',$request->data['id'])->first();
        $auto_report_type = $request->data['auto_report_type'];
        $emails = $auto_report_type['emails'];
        $emails = json_encode($emails);
        $report['auto_report'] = 1;
        $report->save();
        $auto_report = AutoReport::create([
            'report_id' => $report['id'],
            'type' => $auto_report_type['type'],
            'week' => $auto_report_type['week'],
            'emails' => $emails,
            'created_by' => $report['created_by'],
            'day' => $auto_report_type['day'],
            'time' => $auto_report_type['time']
        ]);
        $re = AutoReport::find($auto_report['id']);
        return $re;
    }

    public function createReport(Request $request){
        $user = User::where('id', $request->user()->id)->first();

        $filters = $request->data['filters'];
        $filters = json_encode($filters);

        $columns = $request->data['columns'];
        $columns = json_encode($columns);

        $report_name = $request->data['name'];

        $report = Report::create([
            'title' => $report_name,
            'columns' => $columns,
            'filters' => $filters,
            'created_by' => $user->id,
        ]);

        $rep = Report::get();

        return $rep;
    }

    public function getAssetHistory(Request $request){
        $user = User::where('id', $request->user()->id)->first();

        $history = History::where('asset_id', $request['id'])->with('user')->get();

        return $history;
    }

    // public function importView(Request $request){
    //     return view('importFile');
    // }

    // public function import(Request $request){
    //     Excel::import(new ImportUser, $request->file('file')->store('files'));
    //     return redirect()->back();
    // }

    // public function exportUsers(Request $request){
    //     return Excel::download(new ExportUser, 'users.xlsx');
    // }
}
