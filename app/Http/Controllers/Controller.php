<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Mail;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    //Transactional Emails
    public function transactionalEmail($customMessage, $template = 'email.transactional') {
      //Send an email with mailgun...
      Mail::send('email.transactional', ['customMessage' => $customMessage], function ($message) use ($customMessage) {
        $message->from('karanjot.singh@megger.com', env('COMPANY_NAME'));
        if(isset($customMessage['replyToEmail'])) {
          $message->replyTo($customMessage['replyToEmail'], $customMessage['replyToName']);
        }
        $message->to($customMessage['emailTo']);
        if(isset($customMessage['attach'])) {
          $message->attach($customMessage['attach'], ['as'=>$customMessage['attachFileName']]);
        }
        if(isset($customMessage['cc'])) {
          $message->cc($customMessage['cc']);
        }
        $message->subject($customMessage['subject']);
      });
    }
}
