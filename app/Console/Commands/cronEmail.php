<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reports\Report;
use App\Models\User;
use App\Models\Reports\AutoReport;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Admin\Assets\AssetController;
use App\Models\Assets\Asset;
use App\Jobs\SendEmailJob;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Models\Localities\Region;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
// use App\Http\Controllers\UserController;
use App\Exports\ExportExcel;

class cronEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    // public function handle()
    // {
    //     $reports = Report::get();
    //     $today = date("H:i");
    //     $time = date("H:i", strtotime("08:14:00"));
    //     foreach($reports as $report){
    //         if($report['auto_report']==1){
    //             // info($time);
    //             if($today==$time){
    //                 info([$report['auto_report_type'],$report['auto_report_time']]);
    //             }
    //         }
    //     }
        
    //     return 0;
    // }
    public function sendMail($mail_data) 
    {
        $excel = Excel::store(new ExportExcel($mail_data->assets),$mail_data->filename, 's3');
        sleep(3);
        $path = Storage::disk('s3')->url($mail_data->filename);
        sleep(1);
        $tbody = "<h3><a download='".$mail_data->filename."' href=".$path.">Download Report</a></h3><p>A report has been created. Please check the attached file for the created report.</p>";
        // info($path);
        foreach($mail_data->emails as $email){
            $customMessage = array(
                'headline' => '<h1>There is a new Report!</h1>',
                'message' => $tbody,
                'attach' => $path,
                'attachFileName' => $mail_data->filename,
                'emailTo' => $email,
                'subject' => 'Megger: New Created Report',
                'button' => false
            );
            dispatch(new SendEmailJob($customMessage));
        }
    } 

    public function getAssets($filters)
    {
        
        $query = Asset::query();

        // Limit to correct asset type
        if($filters['asset_type'] == 'unique') {
            $query->where('is_unique', 1);
        }elseif($filters['asset_type'] == 'accessories') {
            $query->where('is_unique', 0)->where('is_accessory', 1);
        }elseif($filters['asset_type'] == 'all') {
            $query->where('is_unique', 0)->where('is_accessory', 0);
        }else {
            $query->where('is_unique', 0)->where('is_accessory', 0);
        }

        // Do we want to see only assets assigned to a certain user
        if(isset($filters['assigned_to']) && $filters['assigned_to'] !== 'null') {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        // Are we filtering by a date?
        if(isset($filters['filter_by_date']) && $filters['filter_by_date'] == 'true') {
            if($filters['date_range'] == 'created') {
                $query->whereDate('created_at', '>=', date('Y-m-d', strtotime($filters['start_date'])))->whereDate('created_at', '<=', strtotime($filters['end_date']));
            }elseif($filters['date_range'] == 'acquired') {
                $query->whereDate('acquisition_date', '>=', date('Y-m-d', strtotime($filters['start_date'])))->whereDate('acquisition_date', '<=', strtotime($filters['end_date']));
            }elseif($filters['date_range'] == 'last_transaction') {
                $query->whereDate('last_transaction_date', '>=', date('Y-m-d', strtotime($filters['start_date'])))->whereDate('last_transaction_date', '<=', strtotime($filters['end_date']));
            }
        }

        // Are we sorting by status?
        if(isset($filters['status']) && $filters['status'] !== 'null') {
            // Do where want a where, or a where not... note this is a hotfix so that we can
            // search "where NOT status === "Not Available" to meet a late game ticket requirement.
            if(isset($filters['status_not'])) {
                $query->whereNotIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        $qualified_assets = $query->get();
        $qualified_ids = [];
        foreach($qualified_assets as $qa) {
            array_push($qualified_ids, $qa->id);
        }

        // Filter by keywords
        if(isset($filters['keyword']) && $filters['keyword'] !== '') {
            $i = 0;
            foreach($filters['selected_fields'] as $key => $field) {
                if($i == 0) {
                    $query->where($field, 'like', '%' . $filters['keyword'] . '%');
                }else {
                    $query->orWhere($field, 'like', '%' . $filters['keyword'] . '%');
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
        if(isset($filters['include_cf']) && $filters['include_cf'] == 'true') {
            // Search Custom Fields
            $options = CustomField::where('default_value', 'like', '%' . $filters['keyword'] . '%')->with('asset')->get();
            $cf_ids = [];
            foreach($options as $opt) {
                array_push($cf_ids, $opt->asset->id);
            }
            if($filters['asset_type'] == 'all') {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 0)->where('is_accessory', 0)->get();
            }elseif($filters['asset_type'] == 'unique') {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 1)->where('is_accessory', 0)->get();
            }else {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 0)->where('is_accessory', 1)->get();
            }
            foreach($assets as $a) {
                array_push($asset_ids, $a->id);
            }
            // Now Search Options
            $options = CustomFieldOption::where('text', 'like', '%' . $filters['keyword'] . '%')->with('field')->with('field.asset')->get();
            $cf_ids = [];
            foreach($options as $opt) {
                array_push($cf_ids, $opt->field->asset->id);
            }
            if($filters['asset_type'] == 'all') {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 0)->where('is_accessory', 0)->get();
            }elseif($filters['asset_type'] == 'unique') {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 1)->where('is_accessory', 0)->get();
            }else {
                $assets = Asset::whereIn('id', $cf_ids)->where('is_unique', 0)->where('is_accessory', 1)->get();
            }
            foreach($assets as $a) {
                array_push($asset_ids, $a->id);
            }
        }

        // Now grab all relevant assets
        if($filters['asset_type'] == 'all') {
            $cf_assets = Asset::whereIn('id', $asset_ids)->where('is_unique', 0)->where('is_accessory', 0)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
        }elseif($filters['asset_type'] == 'unique') {
            $cf_assets = Asset::whereIn('id', $asset_ids)->where('is_unique', 1)->where('is_accessory', 0)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
        }else {
            $cf_assets = Asset::whereIn('id', $asset_ids)->where('is_unique', 0)->where('is_accessory', 1)->with('customFields')->with('customFields.options')->with('accessories')->with('accessories.asset')->with('region')->with('territory')->with('manufacturingSite')->with('repairSites')->with('repairSites.site')->with('assignee')->with('assignee.primaryAddress')->with('assignee.region')->orderBy('created_at', 'ASC')->get();
        }
    
        // Now add all ids to an array
        $asset_ids = [];
        foreach($searched_assets as $a) {
            array_push($asset_ids, $a->id);
        }
        foreach($cf_assets as $a) {
            array_push($asset_ids, $a->id);
        }
        $fields = ['mn_number','asset_title','serial_number','description','status', 'condition','assigned_to'];
        foreach($filters['selected_fields'] as $field){
            if(!in_array($field, $fields)){
                if($field == 'territory'){
                    info('territory');
                }else if($field == 'owner_region'){
                    array_push($fields, 'owner_region_id');
                }else if($field == 'region'){
                    array_push($fields, "region_id");
                }else{
                    array_push($fields, $field);
                }
            }
        }
        $assets = Asset::select(...$fields)->whereIn('id', $asset_ids)->with('assignee')->with('region')->orderBy('created_at', 'ASC')->get();
        // $assets = $assets->map(function ($asset, $key) use($fields, $ass){
        //     if($asset->assigned_to){
        //         $user = User::where('id', $asset->assigned_to)->first();
        //         $asset->assigned_to = $user['first_name'].' '.$user['last_name'];
        //     }
        //     if(in_array('region_id', $fields)){
        //         $region = Region::where('id', $asset->region_id)->first();
        //         $asset->region = '';
        //     }
        //     if(in_array('owner_region_id', $fields)){
        //         $region = Region::where('id', $asset->region_id)->first();
        //         $asset->owner_region = '';
        //     } 
        //     array_push($ass, $asset);
        // });
        foreach($assets as $asset){
            $region = null;
            if(in_array('region_id', $fields)){
                $asset->region = "";
                if($asset->region_id){
                    $region = $asset->region['region'];
                    $asset->region = $region;
                }
            }
            if(in_array('owner_region_id', $fields)){
                $asset->owner_region = "";
                if($asset->owner_region_id){
                    $owner_region = Region::where('id', $asset->owner_region_id)->first();
                    $asset->owner_region = $owner_region['region'];
                }
            }
            if($asset->assigned_to){
                $asset->assigned_to = $asset->assignee['first_name'].' '.$asset->assignee['last_name'];
            }
            unset($asset->assignee);
            unset($asset->region_id);
            unset($asset->owner_region_id);
        }
        return $assets;
    }

    public function handle()
    {
        // $reports = Report::get();
        $auto_reports = AutoReport::with('user')->get();
        $controller = new Controller();
        // $assetController = new AssetController();
        $excel = null;
        $path = '';

        foreach ($auto_reports as $report) {
            // info($report);
            // $today = date("H:i");
            // $time = date("H:i", strtotime("18:08:00"));
            $time = Carbon::parse($report['time']);            
            // $assetController = new AssetController(Request $request);
            $r = Report::where('id',$report['report_id'])->first();
            $filename = $r['title'].'.xlsx';
            $day = $report['day'];
            $emails = [];
            array_push($emails,$report['user']['email']);
            $additional_emails = json_decode($report['emails'], true);
            if($additional_emails){
                foreach ($additional_emails as $email) {
                    array_push($emails,$email);
                }
            }
            $mail_data = (object) [
                "report" => $report,
                "filename" => $filename,
                "emails" => $emails,
                "assets" => []
            ];
            $filters = json_decode($r['filters'], true);

            $mail_data->assets = $this->getAssets($filters);

            $type = $report['type'];
            // $time = Carbon::parse(date("H:i"));
            // $excel = Excel::download($assets, 'assets.xlsx');

            if($type){
                //Monthly report
                if($type=='monthly'){
                    info('Monthly report');
                    $month_year = date('M-Y');
                    $current_date = date("d-M-Y");
                    $report_date = '';
                    if($report['week']==1){
                        $report_date = date("d-M-Y", strtotime("first ".$day." ".$month_year));
                    }else if($report['week']==2){
                        $report_date = date("d-M-Y", strtotime("second ".$day." ".$month_year));
                    }else if($report['week']==3){
                        $report_date = date("d-M-Y", strtotime("third ".$day." ".$month_year));
                    }else if($report['week']==4){
                        $report_date = date("d-M-Y", strtotime("fourth ".$day." ".$month_year));
                    }

                    if($report_date == $current_date && $time->isCurrentMinute()){
                        $this->sendMail($mail_data);
                    }

                    // info([$report_date, $current_date]);
                    
                    // info($report);
                    // $date = date('d');
                    // if($time->isCurrentMinute() && $date=='01'){
                    //     $email = $controller->transactionalEmail($customMessage);
                    // }
                }else if($type=='weekly'){
                    info('Weekly report');
                    $current_day = date('l');
                    if($day==$current_day && $time->isCurrentMinute()){
                        $this->sendMail($mail_data);
                    }
                }
                // else if($type[0]!="weekly" && $type[0]!="monthly"){
                //     foreach($type as $d){
                //         $day = date('l');
                //         if($time->isCurrentMinute() && $d==$day){
                //             // info($report);
                //             $email = $controller->transactionalEmail($customMessage);  
                //         }
                //     }  
                // }
            }
            


            // $assets = Asset::take(10);
            // info($assets);
        }

        // foreach($emails as $key => $email) {
        //     // Send an email
        //     $customMessage = array(
        //         'headline' => $headline,
        //         'message' => '<p>'.ucfirst($user->first_name.' '.$user->last_name).' has created a new damage report.</p>
        //         <ol>'.$tbody.'</ol>'.$damageAddition,
        //         'emailTo' => $email,
        //         'subject' => 'New Damage Report',
        //         'button' => false
        //     );

        //     $this->transactionalEmail($customMessage);
        // }
        // info(date('l'));   
        // info($today);   

        // $excel = Excel::download($assets, 'assets.xlsx');
        // $path = Storage::disk('uploads')->path('asset_report.xlsx');
        // $path = Storage::disk('uploads')->path('asset_report.xlsx');
        // $excel = Excel::download(new ExportExcel(), 'report.xlsx')->getFile();



        return 0;
    }
}
