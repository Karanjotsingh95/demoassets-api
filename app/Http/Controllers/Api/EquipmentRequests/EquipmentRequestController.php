<?php

namespace App\Http\Controllers\Api\EquipmentRequests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EquipmentRequests\EquipmentRequest;
use App\Models\EquipmentRequests\EquipmentRequestAsset;
use App\Models\ShippingRequests\ShippingRequest;
use App\Models\ShippingRequests\ShippingRequestAsset;
use App\Models\CustomerDropOff\CustomerDropOff;
use App\Models\CustomerDropOff\ExtensionRequest;
use App\Models\CustomerDropOff\CustomerPickUp;
use App\Models\CustomerDropOff\CustomerDropOffAsset;
use App\Models\DamageReports\DamageReport;
use App\Models\History\History;
use App\Jobs\SendEmailJob;

use App\Models\Assets\Asset;
use App\Models\User;

class EquipmentRequestController extends Controller
{
    // Get my equipment requests
    public function getMyRequests(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);
        // Grab their requests
        $requests = EquipmentRequest::where('user_id', $user->id)->with('assets')->with('assets.asset')->with('assets.child')->orderBy('created_at', 'desc')->get();
        return $requests;
    }

    // Get a single equipment request
    public function getEquipmentRequest($id, Request $request) {
        // Grab their requests
        $request = EquipmentRequest::where('id', $id)->with('user')->with('assets')->with('assets.asset')->with('assets.child')->with('assets.asset.region')->with('assets.asset.territory')->with('assets.childProducts')->with('assets.childProducts.assignee')->with('assets.childProducts.region')->with('assets.childProducts.territory')->with('assets.asset.assignee')->with('user')->first();
        return $request;
    }

    // Create an equipment request
    public function create(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);

        $er = EquipmentRequest::create([
            'user_id' => $user->id,
            'type' => $request->req['type'],
            'purpose' => $request->req['purpose'],
            'start_date' => date('Y-m-d', strtotime($request->req['start_date'])),
            'end_date' => date('Y-m-d', strtotime($request->req['end_date'])),
            'shipping_info' => $request->req['shipping_info'],
            'comments' => $request->req['comments']
        ]);

        // Add each selected assets
        foreach($request->req['selected_assets'] as $asset) {
            EquipmentRequestAsset::create([
                'equipment_request_id' => $er->id,
                'asset_id' => $asset['id']
            ]);
        }

        $er = EquipmentRequest::where('id', $er->id)->with('assets')->with('assets.asset')->first();

        $list_assets = '';
        foreach($er->assets as $asset) {
            $list_assets = $list_assets.'<li>'.$asset->asset->asset_title.'</li>';
        }

        $email_body = '<p>Between: '.date('d/m/Y', strtotime($er->start_date)).' and '.date('d/m/Y', strtotime($er->start_date)).'</p>';


        $type = "";
        if($er->type == 'demo'){
            $type = "Demo";
        }
        $email_table = (object)array();     
        $email_table->type = ucfirst($er->type);
        $email_table->purpose = ucfirst($er->purpose);
        $email_table->requestor_name = ucfirst($user->first_name.' '.$user->last_name);   
        $email_table->shipping_address = $er->shipping_info;
        $email_table->time_period_requested = date('m-d-Y', strtotime($er->start_date)).' - '.date('m-d-Y', strtotime($er->end_date));
        $email_table->requestor_comments = $er->comments;
        $email_table->units = $list_assets;

        // $table_headings = '';
        // $table_data = '';
        // foreach ($email_table as $key => $value) {
        //     $key = str_replace("_", " ",$key);
        //     if(gettype($value) == 'array'){
        //         $value = implode(", ", $value);
        //     }
        //     $table_headings = $table_headings.'<th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th>';
        //     $table_data = $table_data.'<td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td>';
        // }
        $table_rows='';

        foreach ($email_table as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $table_rows = $table_rows.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }

        $table = '<table>'.$table_rows.'</table>';
        // $table = '<table><thead><tr>'.$table_headings.'</tr></thead>';
        // $table = $table.'<tbody><tr>'.$table_data.'</tr></tbody></table>';

        // Send email to requestor
        $customMessage = array(
            'headline' => 'New Equipment Request Submitted.',
            'message' => '<p>Your request has been submitted.</p>'.$table,
            'emailTo' => $user->email,
            'subject' => 'Megger: Equipment Request Submitted.',
            'button' => false
        );
        dispatch(new SendEmailJob($customMessage));

        // Email the demo cordinators!
        $dc = User::where('demo_coordinator', 1)->get();

        foreach($dc as $c) {
            // Send an email
            $customMessage = array(
                'headline' => 'There is a New Equipment Request!',
                'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).' has requested the following items between '.date('m/d/Y', strtotime($er->start_date)).' and '.date('m/d/Y', strtotime($er->end_date)).'. Please review and confirm in the Megger Demo App.</p>'.$table,
                'emailTo' => $c->email,
                'subject' => 'Megger: New Equipment Request',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return $er;
    }

    // Create an demo info inquiry
    public function createInfoInquiry(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);

        // Email the demo coordinators!
        $coordinators = User::where('demo_coordinator', 1)->get();

        // Get the table body ready
        $tbody = '';
        $comments = $request->req['comments'];

        foreach($request->req['selected_assets'] as $asset) {

            $ac_date = 'unknown';
            if($asset['acquisition_date']) {
                $ac_date = date('m/d/Y', strtotime($asset['acquisition_date']));
            }

            $tbody = $tbody.'<tr>
            <td class="text-center"><img width="40" height="40" style="height:40px !important;width:40px !important;display:inline-block !imoprtant;" src="'.$asset['asset_image'].'"></td>
            <td class="align-middle">'.$asset['mn_number'].'</td>
            <td class="align-middle">'.$asset['asset_title'].'</td>
            <td class="align-middle">'.$asset['catalog_number'].'</td>
            <td class="text-center align-middle">'.$asset['status'].'</td>
            <td class="text-center align-middle">'.$ac_date.'</td>
            </tr>';
        }

        $tbody = $tbody.'<p>Comments: '.$comments.'</p>';

        foreach($coordinators as $admin) {
            // Send an email
            $customMessage = array(
                'headline' => 'Inquiry (Demo Units For Sale)',
                'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).' has requested information on the potential sale of units listed below.</p>
                <table class="table table-bordered table-striped">
                      <thead>
                        <tr>
                          <th>&nbsp;</th>
                          <th>MN Number</th>
                          <th>Model</th>
                          <th>Catalog Number</th>
                          <th>Status</th>
                          <th>Age</th>
                        </tr>
                      </thead>
                      <tbody>'.
                        $tbody
                      .'</tbody>
                    </table>',
                'emailTo' => $admin->email,
                'subject' => 'Inquiry (Demo Units For Sale)',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return 'Success';
    }

    // Create a special shipping request
    public function createSpecialShippingRequest(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);

        // Email the demo coordinators!
        $coordinators = User::where('demo_coordinator', 1)->get();

        // Get the table body ready
        $asset = $request->req['selected_assets'];
        $tbody = '<tr>
            <td class="text-center"><img width="40" height="40" style="height:40px !important;width:40px !important;display:inline-block !imoprtant;" src="'.$asset['asset_image'].'"></td>
            <td class="align-middle">'.$asset['asset_title'].'</td>
            <td class="align-middle">'.$asset['catalog_number'].'</td>
            <td class="text-center align-middle">'.$asset['status'].'</td>
            </tr>';

        $unit_ready = 'NO';
        if($request->req['unit_ready']) {
            $unit_ready = 'YES';
        }

        $lift_gate_pickup = 'NO';
        if($request->req['lift_gate_pickup']) {
            $lift_gate_pickup = 'YES';
        }

        $lift_gate_delivery = 'NO';
        if($request->req['lift_gate_delivery']) {
            $lift_gate_delivery = 'YES';
        }

        foreach($coordinators as $admin) {
            // Send an email
            $customMessage = array(
                'headline' => 'Inquiry (Special Shipping Request)',
                'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).' has a speical shipping request regarding the units listed below.</p>
                <p>
                    <strong>MN Number: </strong> '.$request->req['mn_number'].'<br>
                    <strong>Date Needed By: </strong> '.date('m/d/Y', strtotime($request->req['mn_number'])).'<br>
                    <strong>Unit Ready: </strong> '.$unit_ready.'<br>
                    <strong>Lift Gate (pickup): </strong> '.$lift_gate_pickup.'<br>
                    <strong>Lift Gate (delivery): </strong> '.$lift_gate_delivery.'<br>
                    <strong>Shipping From: </strong> '.$request->req['shipping_from'].'<br>
                    <strong>Shipping To: </strong> '.$request->req['shipping_to'].'<br>
                </p>
                <hr style="margin:20px 0px;">
                <table class="table table-bordered table-striped">
                      <thead>
                        <tr>
                          <th>&nbsp;</th>
                          <th>Model</th>
                          <th>Catalog Number</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>'.
                        $tbody
                      .'</tbody>
                    </table>',
                'emailTo' => $admin->email,
                'subject' => 'Inquiry (Special Shipping Request)',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return 'Success';
    }

    // Create a new equipment request
    public function createNewEquipmentRequest(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);

        // Email the demo coordinators!
        $admins = User::where('region_id', $user->region_id)->where('manager', 1)->get();

        // Get the table body ready
        $tbody = '';
        foreach($request->req['selected_assets'] as $asset) {

            $tbody = $tbody.'<tr>
            <td class="align-middle">'.$asset['instrument'].'</td>
            <td class="align-middle">'.$asset['qty'].'</td>
            <td class="align-middle">'.$asset['part_number'].'</td>
            </tr>';
        }

        foreach($admins as $admin) {
            // Send an email
            $customMessage = array(
                'headline' => 'Inquiry (New Instruments For Demo Pool)',
                'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).' has requested new instruments for the demo pool.</p><p><strong>Reason: </strong>'.$request->req['purpose'].'<br><strong>Comments: </strong>'.$request->req['comments'].'</p><hr style="margin:20px 0px">
                <table class="table table-bordered table-striped">
                      <thead>
                        <tr>
                          <th>Instrument</th>
                          <th>QTY</th>
                          <th>Part Number</th>
                        </tr>
                      </thead>
                      <tbody>'.
                        $tbody
                      .'</tbody>
                    </table>',
                'emailTo' => $admin->email,
                'subject' => 'Inquiry (New Instruments For Demo Pool)',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return 'Success';
    }

    // Create a new equipment request
    public function createNewAccessoriesRequest(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);

        // Email the RSM's
        $rsms = User::where('region_id', $user->region_id)->where('manager', 1)->get();

        // Email the demo coordinators
        $demos = User::where('demo_coordinator', 1)->get();

        $admins = [];
        foreach($rsms as $usr) {
            array_push($admins, $usr);
        }
        foreach($demos as $usr) {
            array_push($admins, $usr);
        }

        // Get the selected asset table
        $asset = $request->req['parent_asset'];
        $s_tbody = '<tr>
            <td class="text-center"><img width="40" height="40" style="height:40px !important;width:40px !important;display:inline-block !imoprtant;" src="'.$asset['asset_image'].'"></td>
            <td class="align-middle">'.$asset['asset_title'].'</td>
            <td class="align-middle">'.$asset['catalog_number'].'</td>
            <td class="text-center align-middle">'.$asset['status'].'</td>
            </tr>';


        // Get the table body ready
        $tbody = '';
        foreach($request->req['selected_assets'] as $asset) {

            $tbody = $tbody.'<tr>
            <td class="align-middle">'.$asset['instrument'].'</td>
            <td class="align-middle">'.$asset['qty'].'</td>
            <td class="align-middle">'.$asset['part_number'].'</td>
            </tr>';
        }

        foreach($admins as $admin) {
            // Send an email
            $customMessage = array(
                'headline' => 'Request (New Accessories)',
                'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).' has requested new accessories for the item listed below.</p><p><strong>Reason: </strong>'.$request->req['purpose'].'<br><strong>Comments: </strong>'.$request->req['comments'].'</p><hr style="margin:20px 0px">
                <h4>Parent Asset</h4>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>&nbsp;</th>
                            <th>Model</th>
                            <th>Catalog Number</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>'.
                        $s_tbody
                    .'</tbody>
                </table>
                <hr><h4>Requested Accessories</h4>
                <table class="table table-bordered table-striped">
                      <thead>
                        <tr>
                          <th>Instrument</th>
                          <th>QTY</th>
                          <th>Part Number</th>
                        </tr>
                      </thead>
                      <tbody>'.
                        $tbody
                      .'</tbody>
                    </table>',
                'emailTo' => $admin->email,
                'subject' => 'Request (New Accessories)',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return 'Success';
    }

    // Delete a request
    public function delete($id) {
        $er = EquipmentRequest::where('id', $id)->with('assets')->with('assets.asset')->first();
        $requestor = User::find($er->user_id);
        $requestor = $requestor['first_name']." ".$requestor['last_name'];
        $date_created = date('m-d-y', strtotime($er->created_at));

        // Change asset data before deleting
        foreach($er->assets as $a) {
            if($a->child_asset_id){
                $asset = Asset::where('id',$a->child_asset_id)->first();
                $asset->status = 'Available';
                $asset->send_to = NULL;
                $asset->shipping_notes = NULL;
                $asset->deliver_by = NULL;
                $asset->save();
            }
            $a->asset->status = 'Available';
            $a->asset->send_to = NULL;
            $a->asset->shipping_notes = NULL;
            $a->asset->deliver_by = NULL;
            $a->asset->save();
        }

        // Delete associated assets
        EquipmentRequestAsset::where('equipment_request_id', $er->id)->delete();

        // Delete the request
        $er->delete();

        // Email the admins!
        $dc = User::where('demo_coordinator', 1)->get();

        foreach($dc as $coordinator) {
            // Send an email
            $customMessage = array(
                'headline' => 'An Equipment Request Has Been Deleted',
                'message' => '<p>'.$requestor.' has deleted an equipment request submitted on '.$date_created.'.</p>',
                'emailTo' => $coordinator->email,
                'subject' => 'Megger: Equipment Request Deleted',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return $er;
    }

    // Receive Shipments
    public function getEquipmentReceive(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);
        $assets = [];
        $sr = ShippingRequest::where('ship_to_user_id',$request->user()->id)->where('status','Pending')->with('trackingNumbers')->get();

        $sel_assets = (object)[];
        foreach($sr as $s){
            $shipping_req_asset = ShippingRequestAsset::where('shipping_request_id', $s['id'])->get();
            foreach($shipping_req_asset as $a){
                if($a){
                    $a_id = $a['asset_id'];
                    // if(!in_array($sel_assets,$shipping_req_asset['asset_id'])){
                    //     array_push($sel_assets,[$shipping_req_asset['asset_id'],$s['trackingNumbers']]);
                    // }
                    $d_report = "";
                    if($a['damage_report']){
                        $d_report = $a['damage_report'];
                    }
                    // if(!property_exists($sel_assets, $a_id)){
                        $sel_assets->$a_id = [$s['trackingNumbers'], $s['shipment_notes'],$d_report,$s['ship_to_address']];
                    // }
                }
            }

        }

        foreach($sel_assets as $key => $value){
            $asset = Asset::whereNotNull('send_to')->where('id', $key)->where('send_to', $user->id)->with('receiver')->with('assignee')->with('assignee.primaryAddress')->with('accessories')->with('accessories.asset')->get();
            if(count($asset)>0){
                $asset[0]['trackingNumbers'] = $value[0];
                $asset[0]['notes'] = $value[1];
                $asset[0]['damage_report'] = $value[2];
                $asset[0]['ship_to_address'] = $value[3];
                array_push($assets, $asset[0]);
            }
        }


        $other_assets = Asset::whereNotNull('send_to')->where('send_to', $user->id)->where('status', 'In Transit')->with('receiver')->with('assignee')->with('assignee.primaryAddress')->with('accessories')->with('accessories.asset')->get();
        foreach($other_assets as $asset){
            if(!property_exists($sel_assets, $asset['id']) ){
                array_push($assets, $asset);
            }
        }

        // $pick_ups = CustomerPickUp::where('send_to', $user->id)->get();

        // foreach($pick_ups as $p){
        //     $asset = Asset::where('id',$p['asset_id'])->with('receiver')->with('assignee')->with('assignee.primaryAddress')->with('accessories')->with('accessories.asset')->orderBy('send_to')->get();
        //     $t = (object) array('tracking_number' => $p['tracking_number']);
        //     $asset[0]['trackingNumbers'] = [$t];
        //     array_push($assets, $asset[0]);
        // }




        // $assets = Asset::whereNotNull('send_to')->whereNotNull('deliver_by')->where('send_to', $user->id)->with('receiver')->with('assignee')->with('assignee.primaryAddress')->with('accessories')->with('accessories.asset')->orderBy('send_to')->get();
        // $results = ShippingRequest::where('ship_to_user_id', $user->id)->get();
        
        // if(count($assets)>0){
        //     foreach($assets as $a) {
        //         $shipping_req_asset = ShippingRequestAsset::where('asset_id', $a['id'])->get();
        //         // $a['trackingNumbers'] = $shipping_req_asset;

        //         if($shipping_req_asset[0]){
        //             // foreach($shipping_req_asset as $req){
        //                 $shipping_req = ShippingRequest::where('ship_to_user_id',$request->user()->id)->where('id', $shipping_req_asset[0]['id'])->with('trackingNumbers')->orderBy('id','DESC')->get();
        //                 $a['trackingNumbers'] = $shipping_req[0]['trackingNumbers'];
        //             // }
        //         }
        //             // $shipping_req = ShippingRequest::where('id', $shipping_req_asset->id)->where('ship_to_user_id',$request->user()->id)->orderBy('id','DESC')->take(1)->get();
        //     }
        // }
        return $assets;
    }

    //save receiving equipment data
    public function saveEquipmentReceive(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);

        $assets = array();
        $s = NULL;
        $mainEquipmentDamage = '';
        $damagedAccessories = [];
        $lostMissingAccessories = [];
        $notIncludedAccessories = [];

        $image_1 = NULL;
        $image_2 = NULL;
        $image_3 = NULL;
        $image_4 = NULL;
        $image_5 = NULL;

        $damageAddition = '';
        $allDamageReports =(object)[];

        //Get asset history
        // foreach($request->data as $asset) {  
        //     $a = Asset::where('id',$asset['id'])->first();

        //     //prev state of asset
        //     $prev_holder = null;
        //     if($a['assigned_to']){
        //         $prev_holder = User::where('id', $a['assigned_to'])->first();
        //         $prev_holder = $prev_holder['first_name']." ".$prev_holder['last_name'];
        //     }
        //     $changed_from = (object)[
        //         'assigned_to' => $prev_holder,
        //         'status' => $a['status'],
        //         'due_date' => $a['due_date'],
        //         'condition' => $a['condition'],
        //         // 'send_to' => $a['send_to']
        //     ];

        //     //new state of asset
        //     $current_holder = null;
        //     if($asset['assigned_to']){
        //         $current_holder = User::where('id', $request->user()->id)->first();
        //         $current_holder = $current_holder['first_name']." ".$current_holder['last_name'];
        //     }
        //     $changed_to = (object)[
        //         'assigned_to' => $current_holder,
        //         'status' => $asset['status'],
        //         'due_date' => $asset['due_date'],
        //         'condition' => $asset['condition'],
        //         // 'send_to' => $asset['send_to']
        //     ];

        //     //history of asset
        //     $history = History::create([
        //         'asset_id' => $asset['id'],
        //         'event' => 'Received',
        //         'changed_from' =>  json_encode($changed_from),
        //         'changed_to' =>  json_encode($changed_to),
        //         'action_by' => $request->user()->id
        //     ]);
        // }

        $assets_details=  (object)[];
        $user_email_body = '';
        // Attach the shipment request assets and asset history
        foreach($request->data as $a) {
            $asset = Asset::find($a['id']);

            ///////////////////   ASSET Historu   ///////////////////////
            //prev state of asset
            $prev_holder = null;
            $previous_holder = null;
            if($asset['assigned_to']){
                $previous_holder = User::where('id', $asset['assigned_to'])->first();
                $prev_holder = $previous_holder['first_name']." ".$previous_holder['last_name'];
            }
            $changed_from = (object)[
                'assigned_to' => $prev_holder,
                'status' => $asset['status'],
                'due_date' =>  date('m-d-Y', strtotime($asset['due_date'])),
                'condition' => $asset['condition'],
                // 'send_to' => $a['send_to']
            ];

            //new state of asset
            $current_holder = null;
            if($a['assigned_to']){
                $curr_user = User::where('id', $request->user()->id)->first();
                $current_holder = $curr_user['first_name']." ".$curr_user['last_name'];
            }
            $changed_to = (object)[
                'assigned_to' => $current_holder,
                'status' => $a['status'],
                'due_date' =>  date('m-d-Y', strtotime($a['due_date'])),
                'condition' => $a['condition'],
                // 'send_to' => $asset['send_to']
            ];

            //history of asset
            $history = History::create([
                'asset_id' => $a['id'],
                'event' => 'Received',
                'changed_from' =>  json_encode($changed_from),
                'changed_to' =>  json_encode($changed_to),
                'action_by' => $request->user()->id
            ]);

            ///////////////////////////////////////////////////////////////////


            $assetId = $a['id'];
            $asset->status = $a['status'];
            $asset->condition = $a['condition'];
            if($a['assigned_to']){
                $asset->owner_region_id = $curr_user['region_id'];
                // $current_holder = $curr_user['first_name']." ".$curr_user['last_name'];
            }
            
            // if($a['damageReport'] && !$a['missing_accessory'] && !$a['missing_m_accessory']){
            //     $damageAddition = $damageAddition . '<p><strong>Main Equipment is damaged</strong></p>';
            // }
            // if($a['status']=="Demo"){
                
            //     // $shipping_req = ShippingRequest::where('id', $shipping_req_asset->id)->where('ship_to_user_id',$request->user()->id)->orderBy('id','DESC')->take(1)->get();
            // }
            $asset->send_to = NULL;
            $asset->assigned_to = $request->user()->id;
            $asset->deliver_by = NULL;
            $shipping_req_asset = ShippingRequestAsset::where('asset_id', $a['id'])->get();
            if($shipping_req_asset){
                foreach($shipping_req_asset as $req){
                    $shipping_req = ShippingRequest::where('id', $req->id)->where('ship_to_user_id',$request->user()->id)->orderBy('id','DESC')->first();
                    if($shipping_req){
                        $shipping_req->status = 'Shipped';
                        $shipping_req->save();
                    }
                }
            }
            $damageReport = (object) [
                "report" => "",
                "images" => array()
            ];
            if(array_key_exists('damageReport', $a)) {
                $damageReport->report = $a['damageReport'];
            }
            if(array_key_exists('image_1', $a)) {
                $image_1 = $a['image_1'];
                array_push($damageReport->images, $image_1);
            }
            if(array_key_exists('image_2', $a)) {
                $image_2 = $a['image_2'];
                array_push($damageReport->images, $image_2);
            }
            if(array_key_exists('image_3', $a)) {
                $image_1 = $a['image_3'];
                array_push($damageReport->images, $image_3);
            }
            if(array_key_exists('image_4', $a)) {
                $image_1 = $a['image_4'];
                array_push($damageReport->images, $image_4);
            }
            if(array_key_exists('image_5', $a)) {
                $image_1 = $a['image_5'];
                array_push($damageReport->images, $image_5);
            }

            $missing_optional = array();
            $missing_mandatory = array();
            $damaged_optional = array();
            $damaged_mandatory = array();
            if(array_key_exists('accessories', $a)) {
                foreach($a['accessories'] as $acc) {
                    $accessory = Asset::find($acc['asset']['id']);

                    if($accessory) {

                        $accessory->status = $acc['asset']['new_status'];
                        $accessory->save();
                        // if($acc['asset']['new_status'] == 'Missing'){
                        //     info($acc['asset']);
                        //     info($accessory);
                        // }

                        // Parse to the correct array
                        if($accessory->status == 'Missing') {
                            $lostMissingAccessories[] = $accessory->asset_title;
                            if($accessory->accessory_mandatory){
                                array_push($missing_mandatory,$accessory->asset_title);
                            }else{
                                array_push($missing_optional,$accessory->asset_title);
                            }                        
                        } elseif($accessory->status == 'Damaged') {
                            $damagedAccessories[] = $accessory->asset_title;
                            if($accessory->accessory_mandatory){
                                array_push($damaged_mandatory,$accessory->asset_title);
                            }else{
                                array_push($damaged_optional,$accessory->asset_title);
                            }  
                        } elseif($accessory->status == 'Not Included') {
                            $notIncludedAccessories[] = $accessory->asset_title;
                            // array_push($accessories_not_included,$accessory->asset_title);
                        }
                    }
                }
            }



            /////////////////////////   Email details for the assets /////////////////////////

            $asset_details = (object)array();
            $asset_details->asset_title = $asset->asset_title;
            $asset_details->mn_number = $asset->mn_number;
            $asset_details->assigned_to = $current_holder;
            $asset_details->shipper_name = $prev_holder;
            $asset_details->receiver_name = $current_holder;
            $asset_details->status = $asset->status;
            if($asset->condition == 'HW Repair Required'){
                $asset_details->main_equipment_damage = 'Yes';
            }else{
                $asset_details->main_equipment_damage = 'No';
            }
            // $asset_details->shipping_notes = $asset->shipping_notes;
            $asset_details->equipment_notes = $damageReport->report;

            if(count($missing_mandatory)){
                $asset_details->missing_mandatory = $missing_mandatory;
            }
            if(count($damaged_mandatory)){
                $asset_details->damaged_mandatory = $damaged_mandatory;
            }
            if(count($missing_optional)){
                $asset_details->missing_optional = $missing_optional;
            }
            if(count($damaged_optional)){
                $asset_details->damaged_optional = $damaged_optional;
            }



            $table_content = '';
            foreach ($asset_details as $key => $value) {
                $key = str_replace("_", " ",$key);
                if(gettype($value) == 'array'){
                    $value = implode(", ", $value);
                }
                $table_content = $table_content.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
            }
            

            $a_id = $asset['id'];
            $assets_details->$a_id = $asset_details;
            $tbody = '<table>'.$table_content.'</table>';
            if(count($damageReport->images)>0){
                $img_row = '<h4 style="margin-bottom: 10px;">Damage Images:</h4><div style="display:flex;flex-wrap: wrap;">';
                foreach($damageReport->images as $image){
                    $img_row = $img_row.'<div style="flex: 33.33%; padding:2px;"><img src="'.$image.'" style="margin-right:2px;margin-bottom:10px;border:1px solid #ebebeb;max-width: 200px;height: auto;"></div>';
                }
                $img_row = $img_row.'</div>';
                $tbody = $tbody.$img_row;
            }

            $user_email_body = $user_email_body.'<br>'.$tbody;

            ////////////////////////////////////////////////////////////////////////
  

            if(strlen($damageReport->report)>0 || count($damageReport->images)>0){
                $allDamageReports->$assetId = $damageReport;
            }

            // array_push($allDamageReports, $damageReport);
            
            $asset->save();
            array_push($assets, $asset);

            ////////////////////   email to shipper  //////////////////////
            $customMessage = array(
                'headline' => 'Shipped equipment has been received.',
                'message' => '<p>Equipment shipped has been received. Check details below:</p>'.$tbody,
                'emailTo' => $previous_holder['email'],
                'subject' => 'Megger: Demo Equipment Received',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        $coordinators = User::where('demo_coordinator', 1)->get();
        $emails = [];
        foreach($coordinators as $c) {
            array_push($emails,$c->email);
            // $emails[] = $c->email;
        }
        array_push($emails, $user['email']);


        // Splice in additional emails!!!

        // Get the table body ready
        $tbody = '<p><strong>These instruments are now available under the <a href="'.env('FRONTEND_URL').'">My Equipment page.</a>';
        foreach($request->data as $asset) {
            $assetId = $asset['id'];

            $ac_date = 'unknown';
            if($asset['acquisition_date']) {
                $ac_date = date('m/d/Y', strtotime($asset['acquisition_date']));
            }

            $type = 'Standard Asset';
            if($asset['is_accessory'] == 1) {
                $type = 'Accessory';
            }elseif($asset['is_unique'] == 1) {
                $type = 'Unique Template';
            }

            $tbody = $tbody.'<li><p><strong>'.$asset['asset_title'].' ('.$type.')</strong><br>'.$asset['mn_number'].'</p></li>';
            if($asset['new_condition'] == 'HW Repair Required'){
                $tbody = $tbody.'<p>Hardware repair required for <strong>'.$asset['mn_number'].'</strong></p>';
            }
            if(property_exists($allDamageReports, $assetId)){
                $dReport = $allDamageReports->$assetId;
                if(strlen($dReport->report)>0){
                    $tbody = $tbody.'<p>'.$dReport->report.'</p>';
                }
                if(count($dReport->images)>0){
                    $tbody = $tbody.'<p><strong>Damage Photos</strong></p>';
                    foreach($dReport->images as $image){
                        $tbody = $tbody.'<img src="'.$image.'" style="margin-right:20px;margin-bottom:20px;border:1px solid #ebebeb;max-width: 400px;height: auto;">'; 
                    }
                }
            }
        }

        // Do we need to append a damage addition?
        if(!empty($lostMissingAccessories)) {
            $damageAddition = $damageAddition . '<p><strong>The following accessories are "lost/missing":</strong></p><ul>';
            foreach($lostMissingAccessories as $key => $value) {
                $damageAddition = $damageAddition . '<li>'.$value.'</li>';
            }
            $damageAddition = $damageAddition . '</ul>';
        }
        if(!empty($damagedAccessories)) {
            $damageAddition = $damageAddition . '<p><strong>The following accessories are "damaged":</strong></p><ul>';
            foreach($damagedAccessories as $key => $value) {
                $damageAddition = $damageAddition . '<li>'.$value.'</li>';
            }
            $damageAddition = $damageAddition . '</ul>';
        }
        if(!empty($notIncludedAccessories)) {
            $damageAddition = $damageAddition . '<p><strong>The following accessories are "not included":</strong></p><ul>';
            foreach($notIncludedAccessories as $key => $value) {
                $damageAddition = $damageAddition . '<li>'.$value.'</li>';
            }
            $damageAddition = $damageAddition . '</ul>';
        }

         //For asset History Receive
         
        foreach($emails as $key => $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'Shipped equipment has been received.',
                'message' => '<p>Equipment shipped has been received. Check details below:</p>'.$user_email_body,
                'emailTo' => $email,
                'subject' => 'Megger: Demo Equipment Received',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return $assets;
    }

    public function customerDropOff (Request $request){
        $user = User::find($request->user()->id);
        $customer = $request->data['customer'];
        $accepted = 1;
        $dropoff = CustomerDropOff::create([
            'assigned_to' => $user->id,
            'purpose' => $request->data['status'],
            'start_date' => date('Y-m-d', strtotime($request->data['start_date'])),
            'end_date' => date('Y-m-d', strtotime($request->data['end_date'])),
            'customer_name' => $customer['customerName'],
            'customer_company_name' => $customer['customerCompanyName'],
            'customer_address' => $customer['address'],
            'customer_phone' => $customer['mobile'],
            'accepted' => $accepted,
            'customer_email' => $customer['customerEmail'],
            'comments' => $request->data['additionalComments']
        ]);

        $c = CustomerDropOff::where('id', $dropoff->id)->with('assets')->with('assets.asset')->first();

        

        $tbody = '';
        // // Add each selected assets
        foreach($request->data['assets'] as $asset) {

            //Asset customer dropoff history start
            $changed_from = (object)[]; 
            $changed_to = (object)[];
            $tables = '';

            foreach($c->toArray() as $key => $value) {
                if($key == 'created_at'){
                    $value = date('Y-m-d', strtotime($value));
                }
                if($key!= 'id' && $value!= 'null'&& $value != 0 && $key!="assigned_to" && $key != 'updated_at' && $key != 'assets'){
                    $changed_to->$key = $value;
                }
            }

            if(count((array)$changed_to)>0){
                $history = History::create([
                    'asset_id' => $asset['id'],
                    'event' => 'Drop Off',
                    'changed_from' =>  json_encode($changed_from),
                    'changed_to' =>  json_encode($changed_to),
                    'action_by' => $request->user()->id
                ]);
            }
            //asset history end


            CustomerDropOffAsset::create([
                'customer_drop_off_id' => $c->id,
                'asset_id' => $asset['id']
            ]);
            $a = Asset::find($asset['id']);
            $a->status = $request->data['status'];
            $a->do_due_date = date('Y-m-d', strtotime($request->data['end_date']));
            if($request->data['extendRequest']=='true'){
                $a->due_date = date('Y-m-d', strtotime($request->data['end_date']));
            }
            $a->save();
            $tbody = $tbody.'<li><p><strong>'.$asset['asset_title'].'</strong><br>'.$asset['mn_number'].'</p></li>';


            ///////////////////   Assets table   //////////////////////
            $holder = User::find($asset['assigned_to']);
            $holder = $holder['first_name'].' '.$holder['last_name'];

            $table_data = '';
            $table_asset = (object)array();
            $table_asset->asset_title = $asset['asset_title'];
            $table_asset->mn_number = $asset['mn_number'];
            $table_asset->assigned_to = $holder;
            $table_asset->status = $request->data['status'];
            
            foreach ($table_asset as $key => $value) {
                $key = str_replace("_", " ",$key);
                if(gettype($value) == 'array'){
                    $value = implode(", ", $value);
                }
                $table_data = $table_data.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
            }
            $table_data = '<table>'.$table_data.'</table>';
            $tables = $tables.'<br>'.$table_data;
            /////////////////////////////////////////////////////////////
        }


        $coordinators = User::where('demo_coordinator', 1)->get();

        $emails = [];

        foreach($coordinators as $c) {
            $emails[] = $c->email;
        }

        array_push($emails, $user->email);

        ///////////////////   Customer table   //////////////////////
        $customer_email_table = '';
        $customer_table = (object)array();
        $customer_table->customer_name = $customer['customerName'];
        $customer_table->company_name = $customer['customerCompanyName'];
        $customer_table->customer_mobile = $customer['mobile'];
        $customer_table->customer_email = $customer['customerEmail'];
        $customer_table->customer_address = $customer['address'];
        $customer_table->reason_for_drop_off = $request->data['status'];
        $customer_table->requested_time_period = date('m-d-Y', strtotime($request->data['start_date'])).' - '.date('m-d-Y', strtotime($request->data['end_date']));
        $customer_table->comments = $request->data['additionalComments'];

        foreach ($customer_table as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $customer_email_table = $customer_email_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }
        $customer_email_table = '<table>'.$customer_email_table.'</table>';
        ////////////////////////////////////////////////////////////////


        $tbody = $tbody.'<p>Customer Details:</p>'.'<p>Customer Name: '.$customer['customerName'].'</p>'.'<p>Customer Company: '.$customer['customerCompanyName'].'</p>'.'<p>Customer Phone: '.$customer['mobile'].'</p>'.'<p>Customer Email: '.$customer['customerEmail'].'</p>'.'<p>Customer Address: '.$customer['address'].'</p><br>';
 

        foreach($emails as $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'A Customer Drop-off has been created.',
                'message' => '<p>The following equipment was dropped off with the customer.</p><br>'.$tables.'<p>Customer Details:<p/>'.$customer_email_table,
                'emailTo' => $email,
                'subject' => 'Megger: Customer Drop-off created',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return 'Success';
    }

    public function getDropOffRequests (Request $request){
        $user = User::find($request->user()->id);
        $c = CustomerDropOff::where('assigned_to', $user->id)->with('assets')->with('assets.asset')->get();
        return $c;
    }

    public function extendRequest (Request $request){
        $user = User::find($request->user()->id);
        $asset = $request->data['assets'][0];
        $customer = $request->data['customer'];
        $extend_request = ExtensionRequest::create([
            'asset_id' => $asset['id'],
            'request_status' => 'Pending',
            'start_date' => date('Y-m-d', strtotime($request->data['start_date'])),
            'end_date' => date('Y-m-d', strtotime($asset['do_due_date'])),
            'new_end_date' => date('Y-m-d', strtotime($request->data['end_date'])),
            'comments' => $request->data['additionalComments']
        ]);

        /////////////////////// assets table //////////////////////

        $holder = User::find($asset['assigned_to']);
        $holder = $holder['first_name'].' '.$holder['last_name'];

        $asset_table = '';
        $asset_details = (object)array();
        $asset_details->asset_title = $asset['asset_title'];
        $asset_details->mn_number = $asset['mn_number'];
        $asset_details->assigned_to = $holder;
        $asset_details->status = $asset['status'];
        $asset_details->actual_time_period = date('m-d-Y', strtotime($request->data['start_date'])).' - '.date('m-d-Y', strtotime($asset['do_due_date']));
        $asset_details->updated_time_period = date('m-d-Y', strtotime($request->data['start_date'])).' - '.date('m-d-Y', strtotime($request->data['end_date']));

        foreach ($asset_details as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $asset_table = $asset_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }

        $asset_table = '<table>'.$asset_table.'</table>';

        ///////////////////   Customer table   //////////////////////

        $customer_email_table = '';
        $customer_table = (object)array();
        $customer_table->customer_name = $customer['customerName'];
        $customer_table->company_name = $customer['customerCompanyName'];
        $customer_table->customer_mobile = $customer['mobile'];
        $customer_table->customer_email = $customer['customerEmail'];
        $customer_table->customer_address = $customer['address'];
        $customer_table->comments = $request->data['additionalComments'];

        foreach ($customer_table as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $customer_email_table = $customer_email_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }
        $customer_email_table = '<table>'.$customer_email_table.'</table>';
        ////////////////////////////////////////////////////////////////


        $coordinators = User::where('demo_coordinator', 1)->get();
        $emails = [];
        foreach($coordinators as $c) {
            array_push($emails,$c->email);
        }
        array_push($emails, $user->email);

        foreach($emails as $email) {
            $customMessage = array(
                'headline' => 'Time Extension Request Submitted',
                'message' => 'A time extension request has been submitted for the following customer drop off instrument.'.$asset_table.'<br>'.$customer_email_table,
                'emailTo' => $email,
                'subject' => 'Megger: Extension Request Submitted',
                'button' => false
            );
            dispatch(new SendEmailJob($customMessage));
        }
        return $asset;
    }

    public function customerData (Request $request){
        $customerAsset = CustomerDropOffAsset::where('asset_id',$request['id'])->orderBy('id','DESC')->first();

        $customer = NULL;
        if($customerAsset){
            $customer = customerDropOff::where('id',$customerAsset->customer_drop_off_id)->where('assigned_to',$request->user()->id)->first();
        }
        // $customer
        return $customer;
    }

    public function requestPickUp(Request $request){
        $user = User::find($request->user()->id);

        $customer = $request['data']['customer'];
        $receiver = $request['data']['user'];
        $receiving_user = User::find($receiver['id']);
        $receiving_user = $receiving_user['first_name'].' '.$receiving_user['last_name'];

        $asset = $request['data']['asset'];

        $pick_up = CustomerPickUp::create([
            'assigned_to' => $user->id,
            'asset_id' => $asset['id'],
            'customer_drop_off_id' => $customer['id'],
            'pick_up_date' => date('Y-m-d', strtotime($request->data['pick_up_date'])),
            'shipping_address' => $request->data['shipping_address'],
            'send_to' => $receiver['id'],
            'comments' => $request->data['comments'],
            'pick_up_type' => $request->data['pick_up_type'],
            'status' => 'Pending'
        ]);
        $p = CustomerPickUp::where('id', $pick_up->id)->with('assets')->with('assets.asset')->first();

        $a = Asset::find($p['asset_id']);
        $a->send_to = $receiver['id'];
        $a->save();

        $tbody = '';
        $tbody = $tbody.'<li><p><strong>'.$a['asset_title'].'</strong><br>'.$a['mn_number'].'</p></li>';
        $tbody = $tbody.'<p><strong>Pick-up Details:</strong></p>'.'<p>Pick-up date: '.$request->data['pick_up_date'].'</p>'.'<p>Pick-up Type: '.$request->data['pick_up_type'].'</p>';
        if(strlen($request->data['comments'])>0){
            $tbody = $tbody.'<p>Comments: '.$request->data['comments'].'</p>';
        }

        $tbody = $tbody.'<br><p><strong>Customer Details:</strong></p>'.'<p>Customer Name: '.$customer['customer_name'].'</p>'.'<p>Customer Company: '.$customer['customer_company_name'].'</p>'.'<p>Customer Phone: '.$customer['customer_phone'].'</p>'.'<p>Customer Email: '.$customer['customer_email'].'</p>'.'<p>Customer Address: '.$customer['customer_address'].'</p><br>';
        $tbody = $tbody.'<p><strong>Receiver Details:</strong></p>'.'<p>Name: '.$receiver['first_name'].' '.$receiver['last_name'].'</p>'.'<p>Shipping Address: '.$request->data['shipping_address'].'</p>'.'<p>Email: '.$receiver['email'].'</p>'.'<p>Mobile: '.$receiver['mobile'].'</p><br>';
        

        

        /////////////////////// assets table //////////////////////

        $holder = User::find($asset['assigned_to']);
        $holder = $holder['first_name'].' '.$holder['last_name'];

        $asset_table = '';
        $asset_details = (object)array();
        $asset_details->asset_title = $asset['asset_title'];
        $asset_details->mn_number = $asset['mn_number'];
        $asset_details->assigned_to = $holder;
        $asset_details->status = $asset['status'];
        $asset_details->pick_up_date = date('m-d-Y', strtotime($request->data['pick_up_date']));
        $asset_details->pick_up_type = $request->data['pick_up_type'];
        $asset_details->receiver = $receiving_user;
        // $asset_details->requested_time_period = $customer['start_date'].' '.$customer['end_date'];
        $asset_details->shipping_address = $request->data['shipping_address'];
        $asset_details->comments = $request->data['comments'];

        foreach ($asset_details as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $asset_table = $asset_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }

        $asset_table = '<table>'.$asset_table.'</table>';

        ///////////////////   Customer table   //////////////////////
        $customer_email_table = '';
        $customer_table = (object)array();
        $customer_table->customer_name = $customer['customer_name'];
        $customer_table->company_name = $customer['customer_company_name'];
        $customer_table->customer_mobile = $customer['customer_phone'];
        $customer_table->customer_email = $customer['customer_email'];
        $customer_table->customer_address = $customer['customer_address'];
        
        foreach ($customer_table as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $customer_email_table = $customer_email_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }
        $customer_email_table = '<table>'.$customer_email_table.'</table>';
        ////////////////////////////////////////////////////////////////




        $coordinators = User::where('demo_coordinator', 1)->get();
        $emails = [];
        foreach($coordinators as $c) {
            $emails[] = $c->email;
        }
        array_push($emails, $user->email, $receiver['email']);

        foreach($emails as $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'Pick-up Request Submitted.',
                'message' => '<p>A pickup request has been submitted for the following instrument.'.$asset_table.'<br>'.$customer_email_table,
                'emailTo' => $email,
                'subject' => 'Megger: Pick-up Request Submitted',
                'button' => false
            );
            dispatch(new SendEmailJob($customMessage));
            // $this->transactionalEmail($customMessage);
        }

        return $a;
    }

    public function getPickUpRequests(Request $request){

        $pick_ups = CustomerPickUp::get();
        $assets = [];
        // info($pick_ups);
        $asset_ids = (object)array();
        if(count($pick_ups)>0){
            foreach($pick_ups as $p) {
                $asset = Asset::where('id',$p['asset_id'])->with('receiver')->with('assignee')->with('assignee.primaryAddress')->with('accessories')->with('accessories.asset')->orderBy('send_to')->get();
                $asset[0]['shipping_address'] = $p['shipping_address'];
                $asset[0]['pick_up_status'] = $p['status'];
                $asset[0]['tracking_number'] = $p['tracking_number'];
                $asset[0]['pick_up_date'] = $p['pick_up_date'];
                $asset[0]['pick_up_type'] = $p['pick_up_type'];
                $asset[0]['tracking_numbers'] = $p['tracking_numbers'];
                $asset[0]['notes'] = $p['notes'];
                $asset[0]['pick_up_id'] = $p['id'];
                $asset_id = $asset[0]['id'];
                $asset_ids->$asset_id = $asset[0];
            }
            $assets = array_values((array)$asset_ids);
        }
        

        return $assets;
    }

    public function rejectPickUp(Request $request){
        $user = User::find($request->user()->id);
        $asset = $request->data;
        $assignee = User::find($asset['assigned_to']);
        $coordinators = User::where('demo_coordinator', 1)->get();
        $emails = [];
        foreach($coordinators as $c) {
            $emails[] = $c->email;
        }
        array_push($emails, $user->email);
        array_push($emails, $assignee->email);
        // $p = CustomerPickUp::where('id', $request->id)->first();
        // $p['status']= 'Rejected';
        // $p->save();
        // $pi = CustomerPickUp::where('id', $p->id)->get();
        // EquipmentRequestAsset::where('equipment_request_id', $er->id)->delete();
        $p = CustomerPickUp::where('id', $asset['pick_up_id'])->first();

        $changed_from = (object)[
            'pick_up_status' => $p['status'],
            'notes' => $p['notes'],
        ];

        $changed_to = (object)[
            'pick_up_status' => 'Rejected',
            'notes' => $asset['notes'],
        ];

        $history = History::create([
            'asset_id' => $asset['id'],
            'event' => 'Pick-up approval',
            'changed_from' =>  json_encode($changed_from),
            'changed_to' =>  json_encode($changed_to),
            'action_by' => $request->user()->id
        ]);

        $p['status']= 'Rejected';
        $p['notes'] = $asset['notes'];
        $p->save();

        $pi = CustomerPickUp::where('id', $p['id'])->first();

        $tbody = '<p><strong>'.$asset['asset_title'].'</strong><br>'.$asset['mn_number'].'</p>';
        $tbody = $tbody.'<h3>Reason for rejecting request:</h3><p>'. $asset['notes'].'<p>';

        
         /////////////////////// assets table //////////////////////
         $holder = User::find($asset['assigned_to']);
         $holder = $holder['first_name'].' '.$holder['last_name'];
 
         $asset_table = '';
         $asset_details = (object)array();
         $asset_details->asset_title = $asset['asset_title'];
         $asset_details->mn_number = $asset['mn_number'];
         $asset_details->assigned_to = $holder;
         $asset_details->pick_up_status= 'Rejected';
         $asset_details->notes = $asset['notes'];
     
         foreach ($asset_details as $key => $value) {
             $key = str_replace("_", " ",$key);
             if(gettype($value) == 'array'){
                 $value = implode(", ", $value);
             }
             $asset_table = $asset_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
         }
 
         $asset_table = '<table>'.$asset_table.'</table>';
         //////////////////////////////////////////////////////////////////


        foreach($emails as $key => $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'Pick-up request Rejected',
                'message' => '<p>A pickup request has been rejected for the following instrument.</p>'.$asset_table,
                'emailTo' => $email,
                'subject' => 'Megger: Pick-up request Rejected',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return $pi;
    }

    public function startPickUp(Request $request){
        $user = User::find($request->user()->id);
        $asset = $request->data;
        $assignee = User::find($asset['assigned_to']);

        $coordinators = User::where('demo_coordinator', 1)->get();
        $emails = [];
        foreach($coordinators as $c) {
            $emails[] = $c->email;
        }
        array_push($emails, $user->email);
        array_push($emails, $assignee->email);

        $a = Asset::where('id',$asset['id'])->first();
        $p = CustomerPickUp::where('id', $asset['pick_up_id'])->with('assets')->with('assets.asset')->first();


        $changed_from = (object)[
            'pick_up_status' => $p['status'],
            'asset_status' => $a['status'],
            'notes' => $p['notes'],
            'tracking_numbers' => $p['tracking_numbers']
        ];

        $changed_to = (object)[
            'pick_up_status' => $asset['pick_up_status'],
            'asset_status' => 'In Transit',
            'notes' => $asset['notes'],
            'tracking_numbers' => $asset['tracking_numbers']
        ];

        $a->status = 'In Transit';
        $a->save();
        $p['status'] = $asset['pick_up_status'];
        $p['notes'] = $asset['notes'];
        $p['tracking_numbers'] = $asset['tracking_numbers'];
        $p->save();

        $pi = CustomerPickUp::where('id', $p['id'])->first();

        //change history of asset
        $history = History::create([
            'asset_id' => $asset['id'],
            'event' => 'Pick-up approval',
            'changed_from' =>  json_encode($changed_from),
            'changed_to' =>  json_encode($changed_to),
            'action_by' => $request->user()->id
        ]);

        $tbody = '<p><strong>'.$asset['asset_title'].'</strong><br>'.$asset['mn_number'].'</p>';



         /////////////////////// assets table //////////////////////
         $holder = User::find($asset['assigned_to']);
         $holder = $holder['first_name'].' '.$holder['last_name'];
 
         $asset_table = '';
         $asset_details = (object)array();
         $asset_details->asset_title = $asset['asset_title'];
         $asset_details->mn_number = $asset['mn_number'];
         $asset_details->assigned_to = $holder;
         $asset_details->pick_up_status= $asset['pick_up_status'];
         $asset_details->pick_up_date= date('m-d-Y', strtotime($asset['pick_up_date']));
         $asset_details->pick_up_type= $asset['pick_up_type'];
         $asset_details->notes = $asset['notes'];
         $asset_details->tracking_numbers = $asset['tracking_numbers'];
     
         foreach ($asset_details as $key => $value) {
             $key = str_replace("_", " ",$key);
             if(gettype($value) == 'array'){
                 $value = implode(", ", $value);
             }
             $asset_table = $asset_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
         }
 
         $asset_table = '<table>'.$asset_table.'</table>';
         //////////////////////////////////////////////////////////////////




        foreach($emails as $key => $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'Pick-up request Approved',
                // 'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).'A pickup request has been approved for the following instrument.</p>
                'message' => '<p>A pickup request has been approved for the following instrument.</p>'.$asset_table.'<br>',
                'emailTo' => $email,
                'subject' => 'Megger: Pick-up Request Approved.',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return $pi;
    }

    public function getExtensionRequests (Request $request){
        $extension_requests = ExtensionRequest::where('request_status','Pending')->with('asset')->with('asset.assignee')->get();
        return $extension_requests;
    }

    public function approveExtensionRequest (Request $request){
        $user = User::find($request->user()->id);
        $coordinators = User::where('demo_coordinator', 1)->get();

        $req = $request->data;
        $extension_request = ExtensionRequest::where('id',$req['id'])->with('asset')->with('asset.assignee')->first();
        $asset = Asset::where('id', $req['asset']['id'])->first();
        $assignee = User::find($asset['assigned_to']);


        $changed_from = (object)[
            'extension_status' => 'Pending',
            'asset_due_date' =>  date('m-d-Y', strtotime($asset->do_due_date))
        ];

        $changed_to = (object)[
            'pick_up_status' => 'Approved',
            'asset_due_date' =>   date('m-d-Y', strtotime($req['new_end_date']))
        ];

        $history = History::create([
            'asset_id' => $asset['id'],
            'event' => 'Drop Off Time Extension',
            'changed_from' =>  json_encode($changed_from),
            'changed_to' =>  json_encode($changed_to),
            'action_by' => $request->user()->id
        ]);

        $asset->do_due_date = date('Y-m-d', strtotime($req['new_end_date']));
        $asset->save();
        $extension_request['request_status'] = 'Approved';
        $extension_request->save();
        
        $emails = [];
        foreach($coordinators as $c) {
            array_push($emails,$c->email);
        }
        array_push($emails, $assignee->email);

        $tbody = '<p>Extension Request:</p>';
        $tbody = $tbody.'<p><strong>'.$asset['asset_title'].'</strong><br>'.$asset['mn_number'].'<br>Assigned To: '.$asset['assignee']['first_name'].' '.$asset['assignee']['last_name'].'<br>New Drop-off date: '.$req['new_end_date'].'</p>';

 
        /////////////////////// assets table //////////////////////

        $holder = $assignee['first_name'].' '.$assignee['last_name'];

        $asset_table = '';
        $asset_details = (object)array();
        $asset_details->asset_title = $asset['asset_title'];
        $asset_details->mn_number = $asset['mn_number'];
        $asset_details->assigned_to = $holder;
        $asset_details->status = 'Approved';
        $asset_details->comments = $req['comments'];
        // $asset_details->actual_time_period = date('m-d-Y', strtotime($req['start_date'])).' - '.date('m-d-Y', strtotime($req['end_date']));
        $asset_details->updated_time_period = date('m-d-Y', strtotime($req['start_date'])).' - '.date('m-d-Y', strtotime($req['new_end_date']));


        foreach ($asset_details as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $asset_table = $asset_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }

        $asset_table = '<table>'.$asset_table.'</table>';
        //////////////////////////////////////////////////////////////////

        foreach($emails as $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'Equipment Time Extension Approved.',
                'message' => '<p>The following equipment time extension has been approved.</p><br>'.$asset_table,
                'emailTo' => $email,
                'subject' => 'Megger: Equipment Time Extension Approved',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }
        return $asset;
    }

    public function rejectExtensionRequest (Request $request){
        $user = User::find($request->user()->id);
        $coordinators = User::where('demo_coordinator', 1)->get();
        $req = $request->data;
        $emails = [];

        $asset = Asset::where('id', $req['asset']['id'])->with('assignee')->first();
        $assignee = User::find($asset['assigned_to']);
        $extension_request = ExtensionRequest::where('id',$req['id'])->with('asset')->with('asset.assignee')->first();
        $extension_request['request_status'] = 'Rejected';
        $extension_request['comments'] = $req['comments'];
        $extension_request->save();

        $changed_from = (object)[
            'extension_status' => 'Pending',
            'comments' =>  ''
        ];

        $changed_to = (object)[
            'pick_up_status' => 'Rejected',
            'comments' =>   $req['comments']
        ];

        $history = History::create([
            'asset_id' => $asset['id'],
            'event' => 'Extension Request Rejected',
            'changed_from' =>  json_encode($changed_from),
            'changed_to' =>  json_encode($changed_to),
            'action_by' => $request->user()->id
        ]);

        foreach($coordinators as $c) {
            array_push($emails,$c->email);
        }
        array_push($emails, $assignee->email);


        $tbody = '<p>Extension Request:</p>';
        $tbody = $tbody.'<p><strong>'.$asset['asset_title'].'</strong><br>'.$asset['mn_number'].'<br>Assigned To:'.$asset['assignee']['first_name'].' '.$asset['assignee']['last_name'].'</p>'; 
        $tbody = $tbody.'<p>Comments: '.$req['comments'].'</p>';

        
        /////////////////////// assets table //////////////////////

        $holder = $assignee['first_name'].' '.$assignee['last_name'];

        $asset_table = '';
        $asset_details = (object)array();
        $asset_details->asset_title = $asset['asset_title'];
        $asset_details->mn_number = $asset['mn_number'];
        $asset_details->assigned_to = $holder;
        $asset_details->status = 'Rejected';
        $asset_details->comments = $req['comments'];
        $asset_details->actual_time_period = date('m-d-Y', strtotime($req['start_date'])).' - '.date('m-d-Y', strtotime($req['end_date']));
        $asset_details->requested_time_period = date('m-d-Y', strtotime($req['start_date'])).' - '.date('m-d-Y', strtotime($req['new_end_date']));

        foreach ($asset_details as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $asset_table = $asset_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }

        $asset_table = '<table>'.$asset_table.'</table>';
        //////////////////////////////////////////////////////////////////


        foreach($emails as $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'Equipment Time Extension Rejected.',
                'message' => '<p>The following equipment time extension has been rejected.</p><br>'.$asset_table,
                'emailTo' => $email,
                'subject' => 'Megger: Equipment Time Extension Rejected.',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return $extension_request;
    }

    public function submitDamageReport (Request $request){
        $user = User::find($request->user()->id);

        // Start a var to hold damaged Accessories
        $damagedAccessories = [];
        $lostMissingAccessories = [];
        $notIncludedAccessories = [];
        $damageAddition = '';

        $image_1 = NULL;
        $image_2 = NULL;
        $image_3 = NULL;
        $image_4 = NULL;
        $image_5 = NULL;
        $emails = [];
        array_push($emails, $user->email);

        $images = [];
        $damageReport = (object) [
            "report" => "",
            "images" => array()
        ];
        $main_equipment_damage = null;
        // Attach the shipment request assets
        $damageType = "";
        $assignee = '';
        foreach($request->data['selectedAssets'] as $asset) {
            $assignee = User::find($asset['assigned_to']);
            $assignee = $assignee['first_name'].' '.$assignee['last_name'];
            $damageType = $asset['damageType'];

            if(count($asset['additional_emails'])>0){
                array_push($emails,...$asset['additional_emails']);
            }
            if($asset['new_condition'] == 'HW Repair Required'){
                $main_equipment_damage = 'Yes';
            }
    
            // $damageReport = NULL;
            if(array_key_exists('damageReport', $asset)) {
                $damageReport->report = $asset['damageReport'];
            }
            // if(array_key_exists('damageReport', $asset)) {
            //     $damageReport = $asset['damageReport'];
            //     array_push($images,$image_1);
            // }
            if(array_key_exists('image_1', $asset)) {
                $image_1 = $asset['image_1'];
                array_push( $damageReport->images,$image_1);
            }
            if(array_key_exists('image_2', $asset)) {
                $image_2 = $asset['image_2'];
                array_push( $damageReport->images,$image_2);
            }
            if(array_key_exists('image_3', $asset)) {
                $image_3 = $asset['image_3'];
                array_push( $damageReport->images,$image_3);
            }
            if(array_key_exists('image_4', $asset)) {
                $image_4 = $asset['image_4'];
                array_push( $damageReport->images,$image_4);
            }
            if(array_key_exists('image_5', $asset)) {
                $image_5 = $asset['image_5'];
                array_push( $damageReport->images,$image_5);
            }
            DamageReport::create([
                'reported_by' => $user->id,
                'asset_id' => $asset['id'],
                'damage_report' => $damageReport->report,
                'image_1' => $image_1,
                'image_2' => $image_2,
                'image_3' => $image_3,
                'image_4' => $image_4,
                'image_5' => $image_5
            ]);

            // $damageAddition = '<p>'.$damageReport.'</p><br>';

            // SHOULD ASSET STATUS CHANGE TO IN TRANSIT IF THE ITEM HAS MISSING/DAMAGED ACCESSORIES???
            // Change the assets status to "In Transit"
            $a = Asset::find($asset['id']);

            $changed_from = (object)[
                'asset_status' => $a->status,
                'asset_contition' => $a->condition,
                'damage_report' =>  ''
            ];
    
            $changed_to = (object)[
                'asset_status' =>$asset['new_status'],
                'asset_contition' => $asset['new_condition'],
                'damage_report' => $damageReport->report
            ];
    
            //change history of asset
            $history = History::create([
                'asset_id' => $asset['id'],
                'event' => 'Damage Report',
                'changed_from' =>  json_encode($changed_from),
                'changed_to' =>  json_encode($changed_to),
                'action_by' => $request->user()->id
            ]);

            $a->status = $asset['new_status'];
            $a->condition = $asset['new_condition'];
            $a->save();


            
            $missing_optional = array();
            $missing_mandatory = array();
            $damaged_optional = array();
            $damaged_mandatory = array();
            // Change the accessories statuses
            if(array_key_exists('accessories', $asset)) {
                foreach($asset['accessories'] as $acc) {
                    $accessory = Asset::find($acc['asset']['id']);
                    if($accessory) {
                        $accessory->status = $acc['asset']['new_status'];
                        $accessory->save();

                        // Parse to the correct array
                        if($accessory->status == 'Missing') {
                            $lostMissingAccessories[] = $accessory->asset_title;
                            if($accessory->accessory_mandatory && !in_array($accessory->asset_title, $missing_mandatory)){
                                array_push($missing_mandatory,$accessory->asset_title);
                            }else{
                                array_push($missing_optional,$accessory->asset_title);
                            }
                        } elseif($accessory->status == 'Damaged') {
                            $damaged_mandatory[] = $accessory->asset_title;
                            if($accessory->accessory_mandatory && !in_array($accessory->asset_title, $damaged_mandatory)){
                                array_push($damaged_mandatory,$accessory->asset_title);
                            }else{
                                array_push($damaged_optional,$accessory->asset_title);
                            }                        
                        } elseif($accessory->status == 'Not Included') {
                            $notIncludedAccessories[] = $accessory->asset_title;
                            // array_push($accessories_not_included,$accessory->asset_title);
                        }
                    }
                }
            }
        }


        // Send email Notifications to demo coord's and add' emails
        $coordinators = User::where('demo_coordinator', 1)->get();


        foreach($coordinators as $c) {
            array_push($emails,$c->email);
        }
        array_push($emails, $user->email);


        // Get the table body ready
        $tbody = '<p>'.$user['first_name'].' '.$user['last_name'].'<strong> has reported damage for the following asset.</strong></p>';
        foreach($request->data['selectedAssets'] as $asset) {

            $ac_date = 'unknown';
            if($asset['acquisition_date']) {
                $ac_date = date('m/d/Y', strtotime($asset['acquisition_date']));
            }

            $type = 'Standard Asset';
            if($asset['is_accessory'] == 1) {
                $type = 'Accessory';
            }elseif($asset['is_unique'] == 1) {
                $type = 'Unique Template';
            }

            $tbody = $tbody.'<li><p><strong>'.$asset['asset_title'].' ('.$type.')</strong><br>'.$asset['mn_number'].'</p></li>';
        }

        // Do we need to append a damage addition?
        if(!empty($lostMissingAccessories)) {
            $damageAddition = $damageAddition . '<p><strong>The following accessories are "lost/missing":</strong></p><ul>';
            foreach($lostMissingAccessories as $key => $value) {
                $damageAddition = $damageAddition . '<li>'.$value.'</li>';
            }
            $damageAddition = $damageAddition . '</ul>';
        }
        if(!empty($damagedAccessories)) {
            $damageAddition = $damageAddition . '<p><strong>The following accessories are "damaged":</strong></p><ul>';
            foreach($damagedAccessories as $key => $value) {
                $damageAddition = $damageAddition . '<li>'.$value.'</li>';
            }
            $damageAddition = $damageAddition . '</ul>';
        }
        if(!empty($notIncludedAccessories)) {
            $damageAddition = $damageAddition . '<p><strong>The following accessories are "not included":</strong></p><ul>';
            foreach($notIncludedAccessories as $key => $value) {
                $damageAddition = $damageAddition . '<li>'.$value.'</li>';
            }
            $damageAddition = $damageAddition . '</ul>';
        }


        if($image_1 || $image_2 || $image_3 || $image_4 || $image_5) {
            $damageAddition = $damageAddition . '<p><strong>Damage Photos</strong></p>';
        }

        if($image_1) {
            $damageAddition = $damageAddition . '<img src="'.$image_1.'" style="margin-right:20px;margin-bottom:20px;border:1px solid #ebebeb;max-width: 400px;height: auto;">';
        }

        if($image_2) {
            $damageAddition = $damageAddition . '<img src="'.$image_2.'" style="margin-right:20px;margin-bottom:20px;border:1px solid #ebebeb;max-width: 400px;height: auto;">';
        }

        if($image_3) {
            $damageAddition = $damageAddition . '<img src="'.$image_3.'" style="margin-right:20px;margin-bottom:20px;border:1px solid #ebebeb;max-width: 400px;height: auto;">';
        }

        if($image_4) {
            $damageAddition = $damageAddition . '<img src="'.$image_4.'" style="margin-right:20px;margin-bottom:20px;border:1px solid #ebebeb;max-width: 400px;height: auto;">';
        }

        if($image_5) {
            $damageAddition = $damageAddition . '<img src="'.$image_5.'" style="margin-right:20px;margin-bottom:20px;border:1px solid #ebebeb;max-width: 400px;height: auto;">';
        }

        $asset_table = (object)array();
        $asset_table->asset_title = $a['asset_title'];
        $asset_table->mn_number = $a['mn_number'];
        $asset_table->assigned_to = $assignee;
        $asset_table->damage_type = $damageType;
        $asset_table->equipment_notes = $damageReport->report;
        //missing accessories
        if(count($missing_mandatory)){
            $asset_table->mandatory_accessories_missing = $missing_mandatory;
        }
        if(count($damaged_mandatory)){
            $asset_table->mandatory_accessories_damaged = $damaged_mandatory;
        }
        if(count($missing_optional)){
            $asset_table->optional_accessories_missing = $missing_optional;
        }
        if(count($damaged_optional)){
            $asset_table->optional_accessories_damaged = $damaged_optional;
        }
        if($main_equipment_damage){
            $asset_table->main_equipment_damage = 'Yes';
        }else{
            $asset_table->main_equipment_damage = 'No';
        }
        $table_content = '';
        foreach ($asset_table as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $table_content = $table_content.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }
        $table_content = '<table>'.$table_content.'</table>';

        $damage_images = '';
        if(count($damageReport->images)>0){
            $img_row = '<h4 style="margin-bottom: 10px;">Damage Images:</h4><div style="display:flex;flex-wrap: wrap;">';
            foreach($damageReport->images as $image){
                $img_row = $img_row.'<div style="flex: 33.33%; padding:2px;"><img src="'.$image.'" style="margin-right:2px;margin-bottom:10px;border:1px solid #ebebeb;max-width: 200px;height: auto;"></div>';
            }
            $img_row = $img_row.'</div>';
            $damage_images = $img_row;
        }
        



        foreach($emails as $key => $email) {
            // Send an email
            $customMessage = array(
                'headline' => 'A problem has been reported.',
                // 'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).' has created a new damage report.</p>
                // <ol>'.$tbody.'</ol>'.$damageAddition,
                'message' => '<p>Please see details below for problem reported.</p>'.$table_content.'<br>'.$damage_images,
                'emailTo' => $email,
                'subject' => 'Megger: Equipment Problem Reported',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return 'Success';
    }
}
