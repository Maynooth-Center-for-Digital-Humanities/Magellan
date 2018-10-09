<?php

namespace App\Http\Controllers\Auth;

use App\PasswordReset;
use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use App\Helpers\PrepareOutputTrait;
use Illuminate\Http\Request;
use App\Notifications\ResetUserPasswordSuccess;
use Illuminate\Support\Facades\Hash;
use DB;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    * @param  [string] email
    * @return [string] message
    */

    use ResetsPasswords;
    use PrepareOutputTrait;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function passwordReset(Request $request) {
      $request->validate([
          'email' => 'required|string|email',
          'password' => 'required|string|confirmed',
          'token' => 'required|string'
      ]);

      $passwordReset = PasswordReset::where([
          ['email', $request->email]
      ])->first();

      $validateToken = Hash::check($request->token, $passwordReset->token);
      if (!$validateToken || !$passwordReset) {
        $error = 'The provided password reset token is invalid.';
        return $this->prepareResult(false, [], $error, $error);
      }

      $updated_time = $passwordReset->created_at;
      $nextHour = date("Y-m-d H:i:s",strtotime("+60 minutes",strtotime($updated_time)));
      $currentTime = date("Y-m-d H:i:s");

      if (strtotime($currentTime)>strtotime($nextHour)) {
          $error = 'The provided password reset token has expired. <br/> To reset your password you must request for a new password restore <a href="/password-restore">email</a>.';
          $deleteQuery = 'DELETE from `password_resets` WHERE `email`=?';
          DB::delete($deleteQuery, ['email', $request->email]);
          $this->prepareResult(false, [], $error, $error);
      }

      $user = User::where('email', $passwordReset->email)->first();
      if (!$user) {
        $error = "We can't find a user account matching the provided e-mail address.";
        $this->prepareResult(false, [], $error, $error);
      }

      $user->password = bcrypt($request->password);
      $user->save();
      $deleteQuery = 'DELETE from `password_resets` WHERE `email`=?';
      DB::delete($deleteQuery, ['email', $request->email]);
      $user->notify(new ResetUserPasswordSuccess($user));

      return $this->prepareResult(true, [], [], 'Your password has been successfully reset.');
    }
}
