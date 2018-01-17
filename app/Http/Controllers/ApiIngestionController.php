<?php

namespace App\Http\Controllers;

use Validator;
use App\User as User;
use Hash;
use Illuminate\Validation\Rule;
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
                'api_version' => 'required|string|max:255',
                'collection' => 'required|string|max:255',
                'copyright_statement' => 'required|string|max:1500',
                'creator' => 'nullable|string|max:255',
                'creator_gender' => Rule::in(['Female', 'Male']),
                'creator_location' => 'string|max:255',
                'date_created' => 'date|date_format:Y-m-d',
                'description' => 'string|max:1500',
                'doc_collection' => 'string|max:255',
                'language' => 'required|alpha|max:2',
                'letter_ID' => 'required|integer',
                'modified_timestamp' => 'date|required|date_format:Y-m-d\TH:i:sP',
                'number_pages'=>'required|integer',
                'pages.*.archive_filename'=>'required|max:255',
                'pages.*.contributor'=>'required|max:255',
                'pages.*.doc_collection_identifier'=>'required|max:500',
                'pages.*.last_rev_timestamp'=>'date|required|date_format:Y-m-d\TH:i:sP',
                'pages.*.original_filename'=>'required|max:255',
                'pages.*.page_count'=>'required|integer',
                'pages.*.page_id'=>'required|integer',
                'pages.*.rev_ID'=>'required|integer',
                'pages.*.rev_name'=>'required|max:255',
                'pages.*.transcription'=>'required|max:1500',
                'recipient'=>'required|max:255',
                'recipient_location'=>'required|max:255',
                'request_time'=>'date|required|date_format:Y-m-d\TH:i:sP',
                'source'=>'required|max:255',
                'terms_of_use'=>'required|max:1',
                'time_zone'=>'required|max:255',
                'topics.*.topic_ID'=>'required|integer',
                'topics.*.topic_name'=>'required|max:255',
                'type'=>'required|max:255',
                'user_id'=>'required|max:15',
                'year_of_death_of_author'=>'required|max:4',
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
            $coll =  $entry;
        } else {
            $coll =  "";
            $msg = "Entry not found";
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
            $entry->save();
            return $this->prepareResult(true,$entry, $error['errors'],"Entry created");

        }

    }



}
