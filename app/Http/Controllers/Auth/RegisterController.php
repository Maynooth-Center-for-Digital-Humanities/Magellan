<?php

namespace App\Http\Controllers\Auth;

use App\Notifications\UserRegisteredSuccessfully;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use App\User;
use App\Role;
use App\Helpers\PrepareOutputTrait;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;
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

    /**
     * Register new user account
     *
     * @param  Request $request
     * @return outputTrait
     */
    public function register(Request $request)
    {

        $validatedData = $request->validate([
          'name' => 'required|string|max:255',
          'email' => 'required|string|email|max:255|unique:users',
          'password' => 'required|string|min:6|confirmed',
        ]);
        try {
            $validatedData['password'] = bcrypt(array_get($validatedData, 'password'));
            $validatedData['activation_code'] = str_random(30).time();

            // remove when email service is added as default status is set to 0
            //$validatedData['status'] = 1;

            $user = User::create($validatedData);
            $user->roles()
                ->attach(Role::where('name', 'transcriber')->first());

            // needs to be rewritten to be more dynamic
            if ($request->input('license_agreement')===true) {
              $user->rights()->sync([1],false);
            }
            if ($request->input('subscribe_to_newsletter')===true) {
              $user->rights()->sync([3],false);
            }

            // send confirmation email
            $user->notify(new UserRegisteredSuccessfully($user));

        } catch (\Exception $exception) {
            return $this->prepareResult(false, [], 'Unable to create new user.', $exception);
        }

        /* when email service is configured enable to line below to send mail notifications
         $user->notify(new UserRegisteredSuccessfully($user));
        */
        return $this->prepareResult(true, [], [],'New user account created successfully. Please check your email to activate your new account.');

    }

    /**
    * Activate the user with given activation code.
    * @param string $activationCode
    * @return outputTrait
    */
   public function activateUser(string $activationCode)
   {
       try {
           $user = app(User::class)->where('activation_code', $activationCode)->first();
           if (!$user) {
             return $this->prepareResult(false, $activationCode, "The provided activation code doesn't match any user in our database", []);
           }
           else if ($user->status===1) {
             return $this->prepareResult(false, [], "This user account has already been activated. Please login to start using your account", []);
           }
           else {
             $user->status = 1;
             $user->save();
           }

       } catch (\Exception $exception) {

           return $this->prepareResult(false, [], "Undefined error", $exception);
       }
       return $this->prepareResult(false, [], "User account was activated successfully. Please login to start using your new account.", []);
   }
}
