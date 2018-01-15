<?php

namespace App\Http\Controllers;

use Validator;
use App\User;
use Hash;
use App\Entry;
use Illuminate\Http\Request;


class ApiIngestionController extends Controller
{

public function accessToken(Request $request)

    {

        $validate = $this->validations($request,"login");

        if($validate["error"]){

            return $this->prepareResult(false, [], $validate['errors'],"Error while validating user");

        }

        $user = User::where("email",$request->email)->first();


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

        }elseif($type == "create entry"){

            $validator = Validator::make($request->all(),[

                'element' => 'required'

            ]);

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

                $errors = $validator->errors();

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

     * @param  \App\Todo  $todo

     * @return \Illuminate\Http\Response

     */

    public function show(Request $request, $id)
    {
        $entry = Entry::where('id',1)->first();

        if( $entry != null) {
            $coll =  $entry;
        }
        else {
            $coll =  "empty bottle";
        };

            return $this->prepareResult(false, $coll, [], "unauthorized","You are not authenticated to view this entry");

    }

    public function store(Request $request)
    {
        $error = $this->validations($request,"create entry");

        if ($error['error']) {

            return $this->prepareResult(false, [], $error['errors'],"Error in creating entry");

        } else {
            $entry = new Entry();
            $entry->element = json_encode($request->element);
            $entry->save();
            return $this->prepareResult(true,$entry, $error['errors'],"Todo created");

        }

    }



}
