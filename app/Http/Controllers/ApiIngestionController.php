<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Validator;
use Hash;
use Illuminate\Validation\Rule;
use App\Entry;
use App\User;
use App\Topic;
use App\EntryTopic;
use App\Helpers;
use DB;
use App\Pages as Pages;
use App\EntryFormats as EntryFormats;
use Illuminate\Http\Request;


class ApiIngestionController extends Controller
{

    use Helpers\PrepareOutputTrait;


    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {

        if (Entry::first() != null) {
            $sort = "asc";
            $paginate = 10;
            if ($request->input('sort') !== "") {
                $sort = $request->input('sort');
            }
            if ($request->input('paginate') !== "") {
                $paginate = $request->input('paginate');
            }
            $coll = Entry::where('current_version', 1)->orderBy('created_at', $sort)->paginate($paginate);
        } else {
            $coll = "empty bottle";
        };

        return $this->prepareResult(true, $coll, [], "All user entries");

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Entry $entry
     * @return \Illuminate\Http\Response
     */

    public function show(Request $request, $id)
    {
        $entry = Entry::where('id', $id)->first();

        $msg = "Entry found";
        $status = true;


        if ($entry != null) {
            $coll = json_decode($entry->element, true);
        } else {
            $coll = "";
            $msg = "Entry not found";
            $status = false;

            abort(404, json_encode($this->prepareResult($status, $coll, [], $msg, "Request failed")));
        };

        return $this->prepareResult($status, $coll, [], $msg, "Request complete");

    }

    public function store(Request $request)
    {
        $error = false;
        $format = json_decode($request->getContent())->type;
        $entry_format = EntryFormats\Factory::create($format);
        $validator = $entry_format->valid($request->all());

        if ($validator->fails()) {

            $error = true;
            $errors = $validator->errors();

            return $this->prepareResult(false, [], $error['errors'], "Error in creating entry");

        } else {

            $entry = new Entry();
            $entry->element = $request->getContent();
            $entry->user_id = Auth::user()->id;
            $entry->current_version = Entry::where('current_version', TRUE)->where('element->document_id', $request->document_id)->count() > 0 ? FALSE : TRUE;
            $entry->save();

            return $this->prepareResult(true, $entry, $error['errors'], "Entry created");

        }

    }

    public function fullsearch(Request $request, $sentence)
    {

        $sanitize_sentence = filter_var($sentence, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

        $match_sql = "match(title,description,text_body) against ('$sanitize_sentence' in boolean mode)";

        $paginate = 10;
        if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
            $paginate = $request->input('paginate');
        }

        $results = Pages::select('entry_id', 'title', 'description', DB::raw($match_sql . "as score"))
            ->whereRaw($match_sql)
            ->orderBy('score', 'desc')->paginate($paginate);

        return $this->prepareResult(true, $results, $sanitize_sentence, "Results created");

    }

    public function search(Request $request, $expr)
    {

        return true;

    }

    public function viewtopics(Request $request, $expr = "")
    {
      if(empty($expr)) {
          $results = array();
          $rootTopics = Topic::select('id', 'name', 'count')->where([
            ['parent_id', '=', '0'],
            ['count', '>', '0'],
            ])->get();

          foreach ($rootTopics as $rootTopic) {
            $rootTopic['children'] = Topic::getTopicsChildren($rootTopic['id']);
            $results[]=$rootTopic;
          }

      } else {

          $topic = Topic::select('id')->where('name','=',$expr)->firstOrFail();
          $results = EntryTopic::select('entry_id')->where('topic_id','=',$topic->id)->get();

      }

      return  $this->prepareResult(true,$results, "No Errors","Results created");
    }

    public function accessToken(Request $request)
    {

        $validate = $this->validations($request, "login");
        if ($validate["error"]) {

            return \Response::json($this->prepareResult(false, [], $validate['errors'], "Error while validating user"), 400);

        }

        $user = User::where("email", $request->email)->first();

        //Fabiano: For security reasons let's return only true or false without any hints on the type of error (pwd/username)
        if ($user) {

            if (Hash::check($request->password, $user->password)) {
                return $this->prepareResult(true, ["accessToken" => $user->createToken('ApiIngestion')->accessToken], [], "User Verified");
            } else {
                return $this->prepareResult(false, [], ["password" => "Wrong passowrd"], "Password not matched");
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

    /**
     * Get a validator for an incoming ApiIngestion request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  $type
     * @return \Illuminate\Contracts\Validation\Validator
     */

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
