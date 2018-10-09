<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use App\Helpers\PrepareOutputTrait;
use Illuminate\Http\Request;
use App\Notifications\resetUserPassword;
use App\User;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;
    use PrepareOutputTrait;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function passwordResetEmail(Request $request) {
      $email = $request->input('email');

      if (!$email || $email===null || $email==="")  {
        return $this->prepareResult(false, [], 'Please provide a valid email address.','');
      }
      else {
        $user = User::where('email', $email)->first();
        if (!$user || $user===null) {
          return $this->prepareResult(false, [], 'There is no user in the system with the email address you provided. Please provide the email address you used during registration','');
        }
        else {
          $token = app('auth.password.broker')->createToken($user);
          $user->notify(new resetUserPassword($user, $token));
          return $this->prepareResult(true, [], [],'An email containing instructions about how to reset your password has been sent to the email address you have provided.');
        }
      }

    }
}
