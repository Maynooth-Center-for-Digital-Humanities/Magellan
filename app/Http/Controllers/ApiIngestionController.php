<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Validator;
use Hash;
use Illuminate\Validation\Rule;
use App\Entry;
use App\User;
use App\EntryFormats as EntryFormats;
use Illuminate\Http\Request;



class ApiIngestionController extends Controller
{

public function accessToken(Request $request)

    {

        $validate = $this->validations($request,"login");

        if($validate["error"]){

           return \Response::json($this->prepareResult(false, [], $validate['errors'],"Error while validating user"),400);

        }

        $user = User::where("email",$request->email)->first();

        //Fabiano: For security reasons let's return only true or false without any hints on the type of error (pwd/username)

        if($user){

            if (Hash::check($request->password,$user->password)) {

                return $this->prepareResult(true, ["accessToken" => $user->createToken('ApiIngestion')->accessToken], [],"User Verified");

            }else{

                return $this->prepareResult(false, [], ["password" => "Wrong passowrd"],"Password not matched");

            }

        }else{

            return $this->prepareResult(false, [], ["email" => "Unable to find user"],"User not found");

        }



    }



    public function resetAccessToken(){


        $user = Auth::user();

        if ($user) {
            Auth::user()->token()->revoke();
            Auth::user()->token()->delete();
        }

        return $this->prepareResult(true, [], ["user" => "User logout"],"User logout");


    }
    /**

     * Get a validator for an incoming ApiIngestion request.

     *

     * @param  \Illuminate\Http\Request  $request

     * @param  $type

     * @return \Illuminate\Contracts\Validation\Validator

     */

    public function validations($request,$type){

        $errors = [ ];

        $error = false;


        if($type == "login"){

            $validator = Validator::make($request->all(),[

                'email' => 'required|email|max:255',

                'password' => 'required',

            ]);

            if($validator->fails()){

                $error = true;

                $errors = $validator->errors();

            }

        }  elseif($type == "create entry"){

            $entry = new Entry();
            $entry->element = $request->getContent();
            $entry->user_id = Auth::user()->id;

            $entry_format_validator =  EntryFormats\Factory::create($entry);

            $validator = Validator::make($request->all(),$entry_format_validator->getValidatorSpec());


            if($validator->fails()){

                $error = true;

                $errors = $validator->errors();

            }

        }elseif($type == "show entry"){

            $validator = Validator::make($request->all(),[

                'id' => 'required',

            ]);

            if($validator->fails()) {

                $error = true;

                $errors = $validator->errors();
            }
        }elseif($type == "store entry"){

            $validator = Validator::make($request->all(),[

                'id' => 'required',
                'element' =>'required'

            ]);



            if($validator->fails()){

                $error = true;

                $errors = $validator->errors() . Entry::validate($request->payload())->errors();

            }

        }

        return ["error" => $error,"errors"=>$errors];

    }

    /**

     * Display a listing of the resource.

     *

     * @param  \Illuminate\Http\Request  $request

     * @return \Illuminate\Http\Response

     */

    private function prepareResult($status, $data, $errors,$msg)

    {
        return ['status' => $status,'data'=> $data,'message' => $msg,'errors' => $errors];

    }

    /**

     * Display a listing of the resource.

     *

     * @param  \Illuminate\Http\Request  $request

     * @return \Illuminate\Http\Response

     */

    public function index(Request $request)
    {

        if(Entry::first() != null) {
                           $coll =  Entry::all();
                        } else {
                             $coll =  "empty bottle";
                            };

        return $this->prepareResult(true,$coll,[],"All user entries");

    }

    /**

     * Display the specified resource.

     *

     * @param  \App\Entry  $entry

     * @return \Illuminate\Http\Response

     */

    public function show(Request $request, $id)
    {
        $entry = Entry::where('id',$id)->first();

        $msg = "Entry found";
        $status = true;


        if( $entry != null) {
            $coll =  json_decode($entry->element,true);
        } else {
            $coll =  "";
            $msg = "Entry not found";
            $status = false;

            abort(404, json_encode($this->prepareResult($status, $coll, [], $msg,"Request failed")));
        };

            return $this->prepareResult($status, $coll, [], $msg,"Request complete");

    }

    public function store(Request $request)
    {
        $error = $this->validations($request,"create entry");

        if ($error['error']) {

            return $this->prepareResult(false, [], $error['errors'],"Error in creating entry");

        } else {
            $entry = new Entry();
            $entry->element = $request->getContent();
            $entry->user_id = Auth::user()->id;
            $entry->save();

            return $this->prepareResult(true,$entry, $error['errors'],"Entry created");

        }

    }



}
