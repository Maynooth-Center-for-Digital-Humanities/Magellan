<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Validator;
use Hash;
use Illuminate\Validation\Rule;
use App\User;
use App\Helpers\PrepareOutputTrait;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;
    use PrepareOutputTrait;

    public function accessToken(Request $request)
    {

        $validate = $this->validations($request, "login");
        if ($validate["error"]) {

            return \Response::json($this->prepareResult(false, [], $validate['errors'], "Error while validating user"), 400);

        }

        $user = User::where("email", $request->email)->first();

        //Fabiano: For security reasons let's return only true or false without any hints on the type of error (pwd/username)
        if ($user) {
          $coll = array();
          if (Hash::check($request->password, $user->password)) {
            $coll = array(
              "userName"=>$user->name,
              "accessToken" => $user->createToken('ApiIngestion')->accessToken,
              "roles"=>$user->roles
            );
            return $this->prepareResult(true,$coll, [], "User Verified");
          } else {
              return $this->prepareResult(false, [], ["password" => "Wrong password"], "Password not matched");
          }
        } else {
          return $this->prepareResult(false, [], ["email" => "Unable to find user"], "User not found");
        }

    }


    public function resetAccessToken()
    {

        $user = Auth::user();

        if ($user) {
            Auth::user()->token()->revoke();
            Auth::user()->token()->delete();
        }

        return $this->prepareResult(true, [], ["user" => "User logout"], "User logout");

    }

    public function validations($request, $type)
    {
        $errors = [];
        $error = false;
        if ($type == "login") {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }

        } elseif ($type == "show entry") {

            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);

            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors();
            }
        } elseif ($type == "store entry") {

            $validator = Validator::make($request->all(), [
                'id' => 'required',
                'element' => 'required'
            ]);


            if ($validator->fails()) {
                $error = true;
                $errors = $validator->errors() . Entry::validate($request->payload())->errors();

            }
        }
        return ["error" => $error, "errors" => $errors];

    }

}
