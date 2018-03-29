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
            Entry::where('current_version', TRUE)->where('element->document_id', $request->document_id)->update(array('current_version'=>FALSE));
            $entry->current_version = TRUE;
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

    public function viewtopicsbyid(Request $request, $ids)
    {
      $ids_arr = explode(",", $ids);
      $entry_ids = EntryTopic::select('entry_id')->whereIn('topic_id',$ids_arr)->get();

      if (count($entry_ids)>0) {
          $sort = "asc";
          $paginate = 10;
          if ($request->input('sort') !== "") {
              $sort = $request->input('sort');
          }
          if ($request->input('paginate') !== "") {
              $paginate = $request->input('paginate');
          }
          $coll = Entry::whereIn('id',$entry_ids)->where('current_version', 1)->orderBy('created_at', $sort)->paginate($paginate);
      } else {
          $coll = "empty bottle";
      };

      return $this->prepareResult(true, $coll, [], "All user entries");
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

    public function sources() {

      $sources = Entry::select(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) as source, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->groupBy(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->orderBy(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->get();

      return $this->prepareResult(true, $sources, [], "All user entries");
    }

    public function authors() {
      $creators = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator')) as creator, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->get();

      return $this->prepareResult(true, $creators, [], "All user entries");
    }

    public function genders() {
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender')) as gender, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->get();

      return $this->prepareResult(true, $genders, [], "All user entries");
    }

    public function languages() {
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language')) as language, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->get();

      return $this->prepareResult(true, $genders, [], "All user entries");
    }

    public function date_created() {
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created')) as date_created, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))"))
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))"))
      ->get();

      return $this->prepareResult(true, $genders, [], "All user entries");
    }

    public function indexfiltered(Request $request)
    {
      $sort = "asc";
      $paginate = 10;
      $keywords_ids = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $date_sent = array();

      if ($request->input('sort')!=="") {
        $sort = $request->input('sort');
      }
      if ($request->input('paginate')!=="") {
        $paginate = $request->input('paginate');
      }
      if ($request->input('keywords')) {
        $keywords_ids = $request->input('keywords');
      }
      if ($request->input('sources')) {
        $sources = $request->input('sources');
      }
      if ($request->input('authors')) {
        $authors = $request->input('authors');
      }
      if ($request->input('genders')) {
        $genders = $request->input('genders');
      }
      if ($request->input('languages')) {
        $languages = $request->input('languages');
      }
      if ($request->input('date_sent')) {
        $date_sent = $request->input('date_sent');
      }

      $entry_ids = array();
      // keywords
      if (count($keywords_ids)>0) {
        $keywords_entry_ids = EntryTopic::select('entry_id as id')->whereIn('topic_id',$keywords_ids)->get();
        $keywords_entry_ids = $keywords_entry_ids->toArray();
        $keywords_entry_ids = $this->returnIdsArray($keywords_entry_ids);
        $entry_ids[] = $keywords_entry_ids;
      }
      // sources
      if (count($sources)>0) {
        $new_sources = $this->inputArraytoString($sources);
        $sources_ids = Entry::select("id")->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) IN (".$new_sources.")"))->get();
        $sources_entry_ids = $sources_ids->toArray();
        $sources_entry_ids = $this->returnIdsArray($sources_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$sources_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $sources_entry_ids;
        }
      }
      // authors
      if (count($authors)>0) {
        $new_authors = $this->inputArraytoString($authors);
        $authors_ids = Entry::select("id")->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))) IN (".$new_authors.")"))->get();
        $authors_entry_ids = $authors_ids->toArray();
        $authors_entry_ids = $this->returnIdsArray($authors_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$authors_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $authors_entry_ids;
        }
      }
      // genders
      if (count($genders)>0) {
        $new_genders = $this->inputArraytoString($genders);
        $genders_ids = Entry::select("id")->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))) IN (".$new_genders.")"))->get();
        $genders_entry_ids = $genders_ids->toArray();
        $genders_entry_ids = $this->returnIdsArray($genders_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$genders_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $genders_entry_ids;
        }
      }
      // languages
      if (count($languages)>0) {
        $new_languages = $this->inputArraytoString($languages);
        $languages_ids = Entry::select("id")->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))) IN (".$new_languages.")"))->get();
        $languages_entry_ids = $languages_ids->toArray();
        $languages_entry_ids = $this->returnIdsArray($languages_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$languages_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $languages_entry_ids;
        }
      }
      // date_created
      if (count($date_sent)>0) {
        $new_date_created = $this->inputArraytoString($date_sent);
        $date_created_ids = Entry::select("id")->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))) IN (".$new_date_created.")"))->get();
        $date_created_entry_ids = $date_created_ids->toArray();
        $date_created_entry_ids = $this->returnIdsArray($date_created_entry_ids);
        if (!empty($entry_ids[0])) {
          $new_ids = array();
          foreach($entry_ids[0] as $entry_id) {
            if (in_array($entry_id,$date_created_entry_ids)) {
              $new_ids[]=$entry_id;
            }
          }
          $entry_ids[0] = $new_ids;
        }
        else {
          $entry_ids[] = $date_created_entry_ids;
        }
      }
      
      if (count($entry_ids)>0) {
          $coll = Entry::whereIn('id',$entry_ids[0])->where('current_version', 1)->orderBy('created_at', $sort)->paginate($paginate);
      } else {
        if (Entry::first() != null) {
          $coll = Entry::where('current_version', 1)->orderBy('created_at', $sort)->paginate($paginate);
        } else {
            $coll = "empty bottle";
        };
      };

      return $this->prepareResult(true, $coll, [], "All user entries");
    }

}
