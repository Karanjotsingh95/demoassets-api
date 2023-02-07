<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Models\Auth\PasswordReset;
use App\Models\User;

class AuthController extends Controller
{
    //Constructor
    public function __construct()
    {
        $this->client = DB::table('oauth_clients')->where('id', 2)->first();
    }

    //Attempt authentication
    protected function authenticate(Request $request)
    {
        $request->request->add([
            'username' => $request->email,
            'password' => $request->password,
            'grant_type' => 'password',
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'scope' => '*'
        ]);

        $proxy = Request::create(
            'oauth/token',
            'POST'
        );

        return Route::dispatch($proxy);
    }

    //Refresh Token
    protected function refreshToken(Request $request)
    {
        $request->request->add([
            'grant_type' => 'refresh_token',
            'refresh_token' => $request->refresh_token,
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
        ]);

        $proxy = Request::create(
            '/oauth/token',
            'POST'
        );

        return Route::dispatch($proxy);
    }

    // Check new account unique fields
    public function checkUniques(Request $request) {
        $email = User::where('email', $request->email)->count();
        $channel = User::where('channel_username', $request->channel)->count();

        $emailValid = 1;
        if($email > 0) {
            $emailValid = 0;
        }

        $channelValid = 1;
        if($channel > 0) {
            $channelValid = 0;
        }

        return [
            'email' => $emailValid,
            'channel' => $channelValid
        ];
    }

    // Create a User
    public function createUser(Request $request) {
        $user = User::create([
            'first_name' => $request->user['first_name'],
            'last_name' => $request->user['last_name'],
            'email' => $request->user['email'],
            'admin' => $request->user['admin'],
            'default_region_id' => $request->user['default_region_id'],
            'password' => bcrypt($request->user['password'])
        ]);
        
        return $user;
    }

    // Create a password reset
    public function forgotPassword(Request $request) {
        if($request->email) {
            $code = uniqid().'-'.uniqid();
            PasswordReset::create([
                'email' => $request->email,
                'token' => $code
            ]);

            // Email the Customer a receipt
            $customMessage = array(
                'headline' => 'Reset Your Password',
                'message' => '<p>Click the link below to reset your password.</p>',
                'emailTo' => $request->email,
                'subject' => 'Reset Your Password',
                'button' => true,
                'buttonText' => 'reset password',
                'buttonLink' => env('FRONTEND_URL').'/forgot-password?code='.$code
            );

            $this->transactionalEmail($customMessage);
        }
    }

    // Check reset code
    public function checkResetCode(Request $request) {
        $code = [];
        if($request->code) {
            $code = PasswordReset::where('token', $request->code)->first();
        }
        return $code;
    }

    // Reset Password
    public function resetPassword(Request $request) {
        // Check for the code... 
        $passwordReset = PasswordReset::where('token', $request->code)->first();
        if($passwordReset) {
            $email = $passwordReset->email;
            $user = User::where('email', $email)->first();
            if($user) {
                $user->password = bcrypt($request->password);
                $user->save();
                $passwordReset->delete();
                return $user;
            }
        }
        return 'error';
    }
}
