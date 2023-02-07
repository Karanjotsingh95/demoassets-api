<?php

namespace App\Http\Controllers\Api\Admin\EquipmentRequests;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assets\Asset;
use App\Models\EquipmentRequests\EquipmentRequest;
use App\Models\EquipmentRequests\EquipmentRequestAsset;
use App\Models\ShippingRequests\ShippingRequest;
use App\Models\ShippingRequests\ShippingRequestAsset;
use App\Models\ShippingRequests\ShippingRequestEmail;
use App\Models\ShippingRequests\ShippingRequestTrackingNumber;
use App\Models\ShippingRequests\ShippingRequestRMANumber;
use App\Models\History\History;
use App\Jobs\SendEmailJob;

use App\Models\User;

class EquipmentRequestController extends Controller
{
    // Get all equipment requests
    public function getRequests(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);
        $requests = [];
        if($user->admin == 1) {
            // Grab their requests
            $requests = EquipmentRequest::orderBy('created_at', 'desc')->with('assets')->with('assets.asset')->with('assets.child')->with('user')->get();
        }
        return $requests;
    }

    // Toggle accepted status
    public function toggleAccepted($id, Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);
        if($user->admin == 1) {
            // Grab the request
            $er = EquipmentRequest::where('id', $id)->with('assets')->with('assets.asset')->with('user')->first();
            // Toggle the status
            if($er->accepted == 1) {
                $er->accepted = 0;
                $er->save();
            }else {
                $er->accepted = 1;
                $er->save();
                $list_assets = '';
                foreach($er->assets as $asset) {
                    $list_assets = $list_assets.'<li>'.$asset->asset->asset_title.'</li>';
                }

                // Email the user
                $customMessage = array(
                    'headline' => 'Equipment Request Accepted',
                    'message' => '<p>You equipment request for the following items between '.date('m/d/Y', strtotime($er->start_date)).' and '.date('m/d/Y', strtotime($er->start_date)).' has been accepted. Please review and confirm in the Megger Admin Portal.</p><p>Type: '.$er->type.'<br>Purpose: '.$er->purpose.'<br>Shipping Information: '.$er->shipping_info.'<br>Admin Feedback: '.$er->admin_feedback.'</p><ul>'.$list_assets.'</ul>',
                    'emailTo' => $er->user->email,
                    'subject' => 'Megger: Equipment Request Accepted',
                    'button' => false
                );

                dispatch(new SendEmailJob($customMessage));
            }
        }
    }

    // Fill an equipment request
    public function fill(Request $request) {
        // How many total assets are requested?
        $totalAssetsRequested = count($request->filledReq['assets']);
        // Set vars for status and conditional counts
        $status = '';
        $alternativeCount = 0;
        $notAvailableCount = 0;
        // How many products are alternative or not available
        foreach($request->filledReq['assets'] as $asset) {
            if($asset['is_alternative'] == 1) {
                $alternativeCount++;
                // Save this er asset
                $erasset = EquipmentRequestAsset::find($asset['id']);
                $erasset->is_alternative = 1;
                $erasset->save();
            }
            if($asset['child_asset_available'] == 0) {
                $notAvailableCount++;
                // Save this er asset
                $erasset = EquipmentRequestAsset::find($asset['id']);
                $erasset->child_asset_available = 0;
                $erasset->save();
            }else {
                // Save this er asset
                $erasset = EquipmentRequestAsset::find($asset['id']);
                $erasset->child_asset_available = 1;
                $erasset->save();
            }
        }
        // If we have no alternative products and all are available,
        // the request is "Accepted"
        if($alternativeCount == 0 && $notAvailableCount == 0) {
            $status = 'Accepted';
        }elseif($notAvailableCount < $totalAssetsRequested) {
            // If we have any unavailable, but SOME available, 
            // the request is "Partially Accepted"
            $status = 'Partially Accepted';
        }
        // Do we have any alternatives? If even one asset is alternative
        // The request is "Alternate Equipment" status
        if($alternativeCount > 0) {
            $status = 'Alternate Equipment';
        }
        // Finally, if nothing is available, the request is "Not Available"
        if($notAvailableCount == $totalAssetsRequested) {
            $status = 'Not Available';
        }

        // Grab the actual request
        $er = EquipmentRequest::find($request->filledReq['id']);
        $er->status = $status;
        $er->save();

        $requestor_comments = "";
        if($er->comments){
            $requestor_comments = $er->comments;
        }

        $assets_table_headings = '';
        $assets_table_data = '';
        $receiver = null;
        // Now find out who owns each item and add a shipping request
        $tables = [];
        $dc = User::where('demo_coordinator', 1)->get();

        $receiver = User::find($er['user_id']);
        $arrival_date = '';


        foreach($request->filledReq['assets'] as $asset) {
            $send_to = '';
            $receiver = '';
            $rec = '';
            $shipping_notes = $request->filledReq['shipping_info'];
            $notify_users = array();
            $deliver_by = null;
            $send_to = $request->filledReq['user_id'];
            $receiver = User::find($send_to);
            $rec = $receiver['first_name'].' '.$receiver['last_name'];
            $shipper = NULL;
            $child_asset_available = $asset['child_asset_available'];
            $child_asset_id = $asset['child_asset_id'];
            $is_alternative = $asset['is_alternative'];
            $notes = '';
            if(array_key_exists('notes', $asset)){
                $notes = $asset['notes'];
            }

            
            if($child_asset_available) {
                $org_asset = $asset;
                // Grab this asset from the db
                $asset = Asset::find($asset['child_asset_id']);
                // Fill the requested assets
                // Grab the EquipmentRequestAsset
                $era = EquipmentRequestAsset::find($org_asset['id']);
                $era->child_asset_available = $org_asset['child_asset_available'];
                if($org_asset['child_asset_available'] == 0) {
                    $era->child_asset_id = NULL;
                }else {
                    $era->child_asset_id = $org_asset['child_asset_id'];
                }
                $era->save();

                $deliver_by = date('Y-m-d', strtotime($request->filledReq['start_date'].' -3 days'));
                $arrival_date = date('m-d-Y', strtotime($deliver_by));
                
                // Grab a copy of the correct asset to modify
                $modifiableAsset = Asset::find($era->child_asset_id);
                // save the equipment request
                $modifiableAsset->deliver_by = $deliver_by;
                $modifiableAsset->shipping_notes = $shipping_notes;
                $modifiableAsset->send_to = $send_to;
                $modifiableAsset->due_date = $er['end_date'];
                $modifiableAsset->save();

                $shipper = User::find($asset['assigned_to']);
                if($shipper){
                    array_push($notify_users, $shipper->email);
                }
                foreach($dc as $d) {
                    if($d->email) {
                        array_push($notify_users, $d->email);
                    }
                }
            }else{
                $asset = Asset::find($asset['asset_id']);
            }

            /////////////////////   Receiver details table (shippers side) ////////////////////////

            $receiver_details_table = '';
            $receiver_details = (object)array();
            $receiver_details->ship_to = $receiver['first_name'].' '.$receiver['last_name'];
            $receiver_details->shipping_address = $er['shipping_info'];
            $receiver_details->arrival_date  =date('m-d-Y', strtotime($deliver_by));
            $receiver_details->receiver_phone = $receiver['mobile'];
            $receiver_details->receiver_email = $receiver['email'];
            $receiver_details->requestor_comments = $requestor_comments;

            foreach ($receiver_details as $key => $value) {
                $key = str_replace("_", " ",$key);
                if(gettype($value) == 'array'){
                    $value = implode(", ", $value);
                }
                $receiver_details_table = $receiver_details_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
            }
            $receiver_details_table = '<table>'.$receiver_details_table.'</table>';

            ///////////////////////////////////////////////////////////////////////////


            /////////// Receiver asset table /////////////////
            $asset_details = (object)array();
            $asset_details->asset_title = $asset['asset_title'];
            $asset_details->mn_number = '';
            $asset_details->request_status = 'Accepted';
            $asset_details->comments = '';
            if($is_alternative == 1){
                $asset_details->request_status = 'Alternate Equipment';
            }else if($child_asset_available == 0){
                $asset_details->request_status = 'Not Available';
                if($notes){
                    $asset_details->comments = $notes;
                }
            }
            $asset_details->shipper = '';
            
            
            if($child_asset_available != 0){
                $asset_details->mn_number = $asset['mn_number'];
                $asset_details->shipper = $shipper['first_name'].' '.$shipper['last_name'];
            }

            $table_headings = '';
            $table_data = '';
            foreach ($asset_details as $key => $value) {
                $key = str_replace("_", " ",$key);
                if(gettype($value) == 'array'){
                    $value = implode(", ", $value);
                }
                $table_headings = $table_headings.'<th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th>';
                $table_data = $table_data.'<td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td>';
            }
            $assets_table_headings = $table_headings;
            $assets_table_data = $assets_table_data.'<tr>'.$table_data.'</tr>';


            /////////////////////// Shipper asset table ////////////////////////
            $shipper_asset = (object)array();
            $shipper_asset->asset_title = $asset['asset_title'];
            $shipper_asset->mn_number = $asset['mn_number'];
            $shipper_asset->request_status = 'Accepted';
            $shipper_asset->comments = '';
            if($is_alternative == 1){
                $shipper_asset->request_status = 'Alternate Equipment';
            }else if($child_asset_available == 0){
                $shipper_asset->request_status = 'Not Available';
                if($notes){
                    $shipper_asset->comments = $notes;
                }
            }
            // $shipper_asset->comments = $shipping_notes;

            $shipper_asset_table = '';
            $th = '';
            $td = '';
            foreach ($shipper_asset as $key => $value) {
                $key = str_replace("_", " ",$key);
                if(gettype($value) == 'array'){
                    $value = implode(", ", $value);
                }
                $th = $th.'<th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th>';
                $td = $td.'<td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td>';
            }
            $shipper_asset_table = '<table><thead><tr>'.$th.'</tr></thead><tbody><tr>'.$td.'</tr></tbody></table>';



            //Email to individual shipper and Demo Coordinators
            foreach($notify_users as $key => $email) {
                // Email the user
                $customMessage = array(
                    'headline' => 'There has been a request to ship the equipment(s) in your possession.',
                    // 'message' => '<p>A shipping request has been initiated for assets in your possession. Please login and navigate to "shipping requests" to fulfill this request.</p>',
                    'message' => '<p> Please see the details of the shipping request below:</p>'.$shipper_asset_table.'<br>'.$receiver_details_table,
                    'emailTo' => $email,
                    'subject' => 'Megger: Request to Ship Equipment in your possession',
                    'button' => false
                );

                dispatch(new SendEmailJob($customMessage));
            }
        }


        $assets_table = '<table><thead><tr>'.$assets_table_headings.'</tr></thead><tbody>'.$assets_table_data.'</tbody></table>';


        ////////////////////   Email that the shipper would get   //////////////////////////////
        $receiver_table = '';
        $receiver_table_details = (object)array();
        $receiver_table_details->purpose = 'Demo';
        $receiver_table_details->receiver_name = $receiver['first_name'].' '.$receiver['last_name'];
        $receiver_table_details->email = $receiver['email'];
        $receiver_table_details->phone = $receiver['mobile'];
        $receiver_table_details->arrival_date =  date('m-d-Y', strtotime($arrival_date));
        $receiver_table_details->shipping_information = $er['shipping_info'];
        $receiver_table_details->requestor_comments = $requestor_comments;

        foreach ($receiver_table_details as $key => $value) {
            $key = str_replace("_", " ",$key);
            if(gettype($value) == 'array'){
                $value = implode(", ", $value);
            }
            $receiver_table = $receiver_table.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
        }
        $receiver_table = '<table>'.$receiver_table.'</table>';

        //email for the one approving the request and DCs
        $user = User::find($request->user()->id);
        $emails = [];
        // array_push($emails,$user->email);
        array_push($emails,$receiver->email);
        foreach($dc as $d) {
            if($d->email) {
                array_push($emails, $d->email);
            }
        }
        $req_status = '<p>Request Status: '.$status.'<p/>';

        

        foreach($emails as $key => $email){
            $customMessage = array(
                'headline' => 'There has been an update to your submitted request.',
                // 'message' => '<p>A shipping request has been initiated for assets in your possession. Please login and navigate to "shipping requests" to fulfill this request.</p>',
                'message' => '<p>Please see the status of your requested equipment below:</p><br>'.$assets_table.'<br>'.$receiver_table,
                'emailTo' => $email,
                'subject' => 'Megger: New Equipment Request Update.',
                'button' => false
            );
            dispatch(new SendEmailJob($customMessage));
        }

        return 'Success!';
    }

    // Get shipping requests
    public function getShippingRequests(Request $request) {
        $user = User::find($request->user()->id);
        // $sr = ShippingRequest::where('ship_to_user_id',$request->user()->id)->where('status','Pending')->with('trackingNumbers')->get();

        // $sel_assets = (object)[];
        // foreach($sr as $s){
        //     $shipping_req_asset = ShippingRequestAsset::where('shipping_request_id', $s['id'])->get();
        //     foreach($shipping_req_asset as $a){
        //         if($a){
        //             $a_id = $a['asset_id'];
        //             $d_report = "";
        //             if($a['damage_report']){
        //                 $d_report = $a['damage_report'];
        //             }
        //             $sel_assets->$a_id = [$s['ship_to_address']];
        //         }
        //     }

        // }
        // $er = EquipmentRequest::find($request->filledReq['id']);
        $assets = Asset::whereNotNull('send_to')->whereNotNull('deliver_by')->where('assigned_to', $user->id)->with('receiver')->with('assignee')->with('assignee.primaryAddress')->with('accessories')->with('accessories.asset')->orderBy('send_to')->get();

        return $assets;
    }

    // Save a shipping request
    public function saveShippingRequest(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);

        $ship_to_user_id = NULL;
        if($request->data['shipTo'] && $request->data['shipTo']['id']) {
            $ship_to_user_id = $request->data['shipTo']['id'];
        }
        $repair_site = NULL;

        $selected_rep_site = NULL;
        if($request->data['type'] == "RMA") {
            $selected_rep_site = $request->data['selected_rep_site']['id'];
            $repair_site = $request->data['selected_rep_site'];
        }
        // Create a shipping request
        $shippingRequest = ShippingRequest::create([
            'shippers_user_id' => $user->id,
            'ship_to_user_id' => $ship_to_user_id,
            'ship_to_address' => $request->data['shippingAddress'],
            'shipment_notes' => $request->data['shipmentNotes'],
            'status' => 'Pending',
            'type' => $request->data['type'],
            'selected_rep_site' => $selected_rep_site,
        ]);

        // Start a var to hold damaged Accessories
        $damagedAccessories = [];
        $lostMissingAccessories = [];
        $notIncludedAccessories = [];

        $image_1 = NULL;
        $image_2 = NULL;
        $image_3 = NULL;
        $image_4 = NULL;
        $image_5 = NULL;
        
        $assets_details=  (object)[];
        $user_email_body = '';
        $emails = [];
        $receiver = (array)[];
        $receiver['first_name'] = "";
        $receiver['last_name'] = "";
        $receiver['email'] = "";
        //For asset History Ship
        foreach($request->data['selectedAssets'] as $asset) {  
            $a = Asset::where('id',$asset['id'])->first();

            //old asset status
            $prev_holder = null;
            if($a['assigned_to'] && $a['assigned_to'] != NULL){
                $prev_holder = User::where('id', $a['assigned_to'])->first();
                $prev_holder = $prev_holder['first_name']." ".$prev_holder['last_name'];
            }
            $changed_from = (object)[
                'assigned_to' => $prev_holder,
                'status' => $a['status'],
                'due_date' => date('m-d-Y', strtotime($a['due_date'])),
                'shipping_notes' => $a['shipping_notes'],
                'condition' => $a['condition']
            ];

            $tr = [];
            foreach($request->data['trackingNumbers'] as $key => $value) {
                array_push($tr, $value);
            }

            //asset new status
            $shipper = null;
            $current_holder = null;
            if($asset['assigned_to'] && $asset['assigned_to']!= NULL){
                $shipper = User::where('id', $asset['assigned_to'])->first();
                $current_holder = $shipper['first_name']." ".$shipper['last_name'];
            }
            $due_date = $asset['due_date'];
            if($request->data['type'] == "Other"){
                $due_date = date('Y-m-d', strtotime('+21 days'));
            }
            if($request->data['type'] != "RMA"){
                $receiver = User::where('id', $asset['send_to'])->first();
            }
            

            $changed_to = (object)[
                'assigned_to' => $current_holder,
                'status' => 'In Transit',
                'due_date' => date('m-d-Y', strtotime($due_date)),
                'shipping_notes' => $asset['shipping_notes'],
                'condition' => $asset['condition'],
                // 'assignee' => $asset['assignee'],
                'receiver' => $receiver['first_name'].' '.$receiver['last_name'],
                'tracking_number' => $tr
            ];

            
            //change history of asset
            $history = History::create([
                'asset_id' => $asset['id'],
                'event' => 'Shipped',
                'changed_from' =>  json_encode($changed_from),
                'changed_to' =>  json_encode($changed_to),
                'action_by' => $request->user()->id
            ]);




            $damageReport = NULL;
            $images = [];
            if(array_key_exists('damageReport', $asset)) {
                $damageReport = $asset['damageReport'];
            }
            if(array_key_exists('image_1', $asset)) {
                $image_1 = $asset['image_1'];
                array_push($images,$image_1);
            }
            if(array_key_exists('image_2', $asset)) {
                $image_2 = $asset['image_2'];
                array_push($images,$image_2);
            }
            if(array_key_exists('image_3', $asset)) {
                $image_3 = $asset['image_3'];
                array_push($images,$image_3);
            }
            if(array_key_exists('image_4', $asset)) {
                $image_4 = $asset['image_4'];
                array_push($images,$image_4);
            }
            if(array_key_exists('image_5', $asset)) {
                $image_5 = $asset['image_5'];
                array_push($images,$image_5);
            }
            ShippingRequestAsset::create([
                'shipping_request_id' => $shippingRequest->id,
                'asset_id' => $asset['id'],
                'damage_report' => $damageReport,
                'image_1' => $image_1,
                'image_2' => $image_2,
                'image_3' => $image_3,
                'image_4' => $image_4,
                'image_5' => $image_5
            ]);
            // $all_damages[$asset['mn_number']=>$damageReport]

            // SHOULD ASSET STATUS CHANGE TO IN TRANSIT IF THE ITEM HAS MISSING/DAMAGED ACCESSORIES???
            // Change the assets status to "In Transit"
            $a = Asset::find($asset['id']);
            if($request->data['type'] == "Other"){
                $a->send_to = $request->data['shipTo']['id'];
                $a->due_date = date('Y-m-d', strtotime('+21 days'));
            }

            if($shippingRequest->type == 'RMA') {
                $a->status = 'Under Repair';
                $a->condition = 'Under Repair';
                $a->save();
            } else {
                $a->status = 'In Transit';
                $a->save();
            }

            $missing_optional = array();
            $missing_mandatory = array();
            $damaged_optional = array();
            $damaged_mandatory = array();
            // $accessories_damaged = array();
            // $accessories_not_included = array();
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


            $asset_details = (object)array();
            $asset_details->asset_title = $a->asset_title;
            $asset_details->mn_number = $a->mn_number;
            $asset_details->assigned_to = $current_holder;
            $asset_details->shipper_name = $user->first_name.' '.$user->last_name;
            if($request->data['type'] == "RMA"){
                $asset_details->repair_site = $repair_site['repair_site_name'];
            }else{
                $asset_details->receiver_name = $receiver['first_name'].' '.$receiver['last_name'];
            }
            $asset_details->shipping_address = $request->data['shippingAddress'];
            if($request->data['type'] == "Standard"){
                $asset_details->arrival_date = date('m-d-Y', strtotime($a->deliver_by));
            }
            if($request->data['type'] == "RMA"){
                foreach($request->data['rmaNumbers'] as $key => $value) {
                    $asset_details->rma_number = $value;
                }
            }
            $asset_details->tracking_number = $tr;
            $asset_details->shipping_notes = $request->data['shipmentNotes'];
            $asset_details->status = $a->status;

            $asset_details->equipment_notes = $damageReport;
            if(count($missing_mandatory)>0){
                $asset_details->mandatory_accessories_missing = $missing_mandatory;
            }
            if(count($damaged_mandatory)>0){
                $asset_details->mandatory_accessories_damaged = $damaged_mandatory;
            }
            if(count($missing_optional)>0){
                $asset_details->optional_accessories_missing = $missing_optional;
            }
            if(count($damaged_optional)>0){
                $asset_details->optional_accessories_damaged = $damaged_optional;
            }
            // if(count($accessories_missing)>0){
            //     $asset_details->accessories_missing = $accessories_missing;
            // }
            // if(count($accessories_damaged)>0){
            //     $asset_details->accessories_damaged = $accessories_damaged;
            // }
            // if(count($accessories_not_included)>0){
            //     $asset_details->accessories_not_included = $accessories_not_included;
            // }

            $table_headings = '';
            $table_data = '';
            $table_content = '';
            foreach ($asset_details as $key => $value) {
                $key = str_replace("_", " ",$key);
                if(gettype($value) == 'array'){
                    $value = implode(", ", $value);
                }
                $table_headings = $table_headings.'<th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th>';
                $table_data = $table_data.'<td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td>';
                $table_content = $table_content.'<tr><th style="font-size:12px; text-transform: capitalize; border: 1px solid #dddddd;text-align: left;">'.$key.'</th><td style="font-size:12px; border: 1px solid #dddddd;text-align: left;">'.$value.'</td></tr>';
            }
            

            // $asset_details['missing_accessories'] = $lostMissingAccessories;
            $a_id = $asset['id'];
            $assets_details->$a_id = $asset_details;

            // $tbody = '<table><thead><tr>'.$table_headings.'</tr></thead>';
            // $tbody = $tbody.'<tbody><tr>'.$table_data.'</tr></tbody></table>';
            $tbody = '<table>'.$table_content.'</table>';
            if(count($images)>0){
                $img_row = '<h4 style="margin-bottom: 10px;">Damage Images:</h4><div style="display:flex;flex-wrap: wrap;">';
                foreach($images as $image){
                    $img_row = $img_row.'<div style="flex: 33.33%; padding:2px;"><img src="'.$image.'" style="margin-right:2px;margin-bottom:10px;border:1px solid #ebebeb;max-width: 200px;height: auto;"></div>';
                }
                $img_row = $img_row.'</div>';
                $tbody = $tbody.$img_row;
            }

            $user_email_body = $user_email_body.'<br>'.$tbody;


            //////////////////////////    shipper email     ///////////////////////////
            $headline = 'Equipment has been shipped.';
            if($shippingRequest->type == 'RMA') {
                $headline = 'RMA has been shipped';
            }

            $subject = 'New Shipment';
            if($shippingRequest->type == 'RMA') {
                $subject = 'New RMA';
            }

            $body_text = 'Requested equipment has been shipped. Shipper will update tracking information as soon as possible in the Demo App. Please check Demo App for updates.';
            // Send an email
            $customMessage = array(
                'headline' => $headline,
                'message' => '<p>'.$body_text.'</p>'.$tbody,
                'emailTo' => $shipper['email'],
                'subject' => 'Megger: New Shipment Created',
                'button' => false
            );
            dispatch(new SendEmailJob($customMessage));
            //////////////////////////////////////////////////////////////////
        } 
        

        // Attach the shipment request assets
        // $all_damages = object[];
        // foreach($request->data['selectedAssets'] as $asset) {
        //     $damageReport = NULL;
        //     if(array_key_exists('damageReport', $asset)) {
        //         $damageReport = $asset['damageReport'];
        //     }
        //     if(array_key_exists('image_1', $asset)) {
        //         $image_1 = $asset['image_1'];
        //     }
        //     if(array_key_exists('image_2', $asset)) {
        //         $image_2 = $asset['image_2'];
        //     }
        //     if(array_key_exists('image_3', $asset)) {
        //         $image_1 = $asset['image_3'];
        //     }
        //     if(array_key_exists('image_4', $asset)) {
        //         $image_1 = $asset['image_4'];
        //     }
        //     if(array_key_exists('image_5', $asset)) {
        //         $image_1 = $asset['image_5'];
        //     }
        //     ShippingRequestAsset::create([
        //         'shipping_request_id' => $shippingRequest->id,
        //         'asset_id' => $asset['id'],
        //         'damage_report' => $damageReport,
        //         'image_1' => $image_1,
        //         'image_2' => $image_2,
        //         'image_3' => $image_3,
        //         'image_4' => $image_4,
        //         'image_5' => $image_5
        //     ]);
        //     // $all_damages[$asset['mn_number']=>$damageReport]

        //     // SHOULD ASSET STATUS CHANGE TO IN TRANSIT IF THE ITEM HAS MISSING/DAMAGED ACCESSORIES???
        //     // Change the assets status to "In Transit"
        //     $a = Asset::find($asset['id']);
        //     if($request->data['type'] == "Other"){
        //         $a->send_to = $request->data['shipTo']['id'];
        //         $a->due_date = date('Y-m-d', strtotime('+21 days'));
        //     }

        //     if($shippingRequest->type == 'RMA') {
        //         $a->status = 'Under Repair';
        //         $a->condition = 'Under Repair';
        //         $a->save();
        //     } else {
        //         $a->status = 'In Transit';
        //         $a->save();
        //     }


        //     // Change the accessories statuses
        //     if(array_key_exists('accessories', $asset)) {
        //         foreach($asset['accessories'] as $acc) {
        //             $accessory = Asset::find($acc['asset']['id']);
        //             if($accessory) {
        //                 $accessory->status = $acc['asset']['new_status'];
        //                 $accessory->save();

        //                 // Parse to the correct array
        //                 if($accessory->status == 'Missing') {
        //                     $lostMissingAccessories[] = $accessory->asset_title;
        //                 } elseif($accessory->status == 'Damaged') {
        //                     $damagedAccessories[] = $accessory->asset_title;
        //                 } elseif($accessory->status == 'Not Included') {
        //                     $notIncludedAccessories[] = $accessory->asset_title;
        //                 }
        //             }
        //         }
        //     }
        //     // $a_id = $a['id'];
        //     // $assets_details->$a_id = $a
        //     // $assets_details->$a_id['damage'] = $$lostMissingAccessories;
        //     // if(!property_exists($assets_details, $asset['id']) ){
        //     //     $assets_details->$a_id['damage'] = $$lostMissingAccessories;
        //     // }
        //     // if(!property_exists($assets_details, $asset['id']) ){
        //     //     $assets_details->$asset['id']['damage'] = $lostMissingAccessories;
        //     // }
        // }
        // info($assets_details);

        // Attach the tracking numbers
        foreach($request->data['trackingNumbers'] as $key => $value) {
            ShippingRequestTrackingNumber::create([
                'shipping_request_id' => $shippingRequest->id,
                'tracking_number' => $value
            ]);
        }

        // Attach RMA numbers
        if($shippingRequest->type == 'RMA') {
            foreach($request->data['rmaNumbers'] as $key => $value) {
                ShippingRequestRMANumber::create([
                    'shipping_request_id' => $shippingRequest->id,
                    'rma_number' => $value
                ]);
            }
        }

        // Attach the additional emails
        foreach($request->data['additionalEmails'] as $key => $value) {
            ShippingRequestEmail::create([
                'shipping_request_id' => $shippingRequest->id,
                'email' => $value
            ]);
        }

        // Do accessory reporting
        foreach($request->data['selectedAssets'] as $selAsset) {
            // Grab the asset
            $asset = Asset::find($selAsset['id']);
            if($shippingRequest->type == 'RMA') {
                $asset->status = 'Under Repair';
                $asset->condition = 'Under Repair';
                $asset->save();
            } else {
                $inTransit = true;
                foreach($selAsset['accessories'] as $acc) {
                    // Grab the accessory
                    $accessory = Asset::find($acc['asset']['id']);
                    if($acc['asset']['new_status'] !== 'Included') {
                        $inTransit = false;
                    }
                }
                if($inTransit) {
                    $asset->status = 'In Transit';
                    $asset->condition = 'Functional';
                    $asset->save();
                }
            }
        }



        // if($shippingRequest->type == 'RMA') {
        //     // Email to the selected site
        //     $emails[] = $request->data['selected_rep_site']['email'];
        // } else {
        //     // Obviously, send to the "send to" user...
        //     $emails[] = $request->data['shipTo']['email'];
        // }


        // Get the table body ready
        $tbody = '<p><strong>These instruments are now marked "In Transit" under the <a href="'.env('FRONTEND_URL').'">My Equipment page.</a> Please confirm delivery in the platform using <a href="'.env('FRONTEND_URL').'/recieve-equipment">Receive Equipment.</a>';
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
        $damageAddition = '';
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

        // Send email Notifications to demo coord's and add' emails
        $emails = [];
        array_push($emails, $user->email);
        if($receiver['email']!=""){
            array_push($emails, $receiver['email']);
        }
        $coordinators = User::where('demo_coordinator', 1)->get();
        foreach($coordinators as $c) {
            array_push($emails, $c->email);
        }
        // Splice in additional emails!!!
        foreach($request->data['additionalEmails'] as $key => $value) {
            $emails[] = $value;
        }

        $headline = 'Equipment has been shipped.';
        if($shippingRequest->type == 'RMA') {
            $headline = 'RMA has been shipped';
        }

        $subject = 'New Shipment';
        if($shippingRequest->type == 'RMA') {
            $subject = 'New RMA';
        }


        $body_text = 'Requested equipment has been shipped. Shipper will update tracking information as soon as possible in the Demo App. Please check Demo App for updates.';
        foreach($emails as $key => $email) {
            // Send an email
            $customMessage = array(
                'headline' => $headline,
                'message' => '<p>'.$body_text.'</p>'.$user_email_body,
                'emailTo' => $email,
                'subject' => 'Megger: New Shipment Created',
                'button' => false
            );

            dispatch(new SendEmailJob($customMessage));
        }

        return $shippingRequest;
    }

    // Get shipping requests as admin
    public function getShippingRequestsAsAdmin(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);
        $results = [];
        if($user->admin == 1) {
            $results = ShippingRequest::with('shipper')->with('receiver')->with('assets')->with('trackingNumbers')->with('additionalEmails')->orderBy('created_at', 'DESC')->get();
        }
        return $results;
    }

    // Get shipments
    public function getShipments(Request $request) {
        // Grab the user
        $user = User::find($request->user()->id);
        $results = ShippingRequest::where('shippers_user_id', $user->id)->with('shipper')->with('receiver')->with('assets')->with('assets.asset')->with('trackingNumbers')->with('additionalEmails')->orderBy('created_at', 'DESC')->get();
        return $results;
    }

    //update tracking numbers for a shipment
    public function updateTrackingNumbers(Request $request) {

        ShippingRequestTrackingNumber::where('shipping_request_id',$request->data['shipment_id'])->delete();

        foreach($request->data['tracking_numbers'] as $key => $value) {
            ShippingRequestTrackingNumber::create([
                'shipping_request_id' => $request->data['shipment_id'],
                'tracking_number' => $value
            ]);
        }
        $results = ShippingRequestTrackingNumber::where('shipping_request_id',$request->data['shipment_id'])->get();

        return $results;

    }

    
}
