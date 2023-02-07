<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Reports\Report;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\cronEmail'
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->everyMinute();

        // $schedule->command('emails:send')->everyMinute();

        $reports = Report::get();
        $schedule->command('emails:send')->everyMinute();
        // foreach ($reports as $report) {
        //     $today = date("H:i");
        //     // $time = date("H:i", strtotime("18:08:00"));
        //     $type = json_decode($report['auto_report_type'], true);

        //     if($type && in_array('monthly', $type)){
        //         $schedule->command('emails:send')->when(function() use($report){
        //             // $time = Carbon::parse($report['auto_report_time']);
        //             $time = Carbon::parse(date("H:i"));
        //             $date = date('d');
        //             if($time->isCurrentMinute() && $date=='01'){
        //                 return true;
        //             }
        //         });           
        //     }else if($type && in_array('weekly', $type)){
        //         $schedule->command('emails:send')->when(function() use($report){
        //             // $time = Carbon::parse($report['auto_report_time']);
        //             $time = Carbon::parse(date("H:i"));
        //             $day = date('l');
        //             if($time->isCurrentMinute() && $day=='Monday'){
        //                 return true;
        //             }
        //         });           
        //     }else if($type && $type[0]!="weekly" && $type[0]!="monthly"){
        //         foreach($type as $d){
        //             $schedule->command('emails:send')->when(function() use($report, $d){
        //                 // $time = Carbon::parse($report['auto_report_time']);
        //                 $time = Carbon::parse(date("H:i"));
        //                 $day = date('l');
        //                 if($time->isCurrentMinute() && $d==$day){
        //                     return true;
        //                 }
        //             }); 
        //         }         
        //     }

        // }
        // $time = '07:06';
        // foreach($reports as $report){
        //     if($report['auto_report']==1){
        //         if($report['auto_report_type']=='monthly')
        //             $schedule->call(function(){
        //                 info('Working');
        //             })->monthlyOn(9, $time);
        //         // info([$report['auto_report_type'],$report['auto_report_time']]);

        //     }
        // }
        // $schedule->call('emails:send')->everyMinutes()->when(function () {
        //     $reports = Report::first();
        //     // $time = '07:06';
        //     if($report['auto_report']==1){
        //         if($report['auto_report_type']=='monthly'){
        //             $time = '07:37';
        //         }
        //         // info([$report['auto_report_type'],$report['auto_report_time']]);
        //     }
        //     if($time){
        //         return true;
        //     }
        //     else{
        //         return false;
        //      }
    
        // });
        // $schedule->call(function(){
        //     $time = '07:28';
        //     info('Working');
        // })->monthlyOn(9, $time);

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
