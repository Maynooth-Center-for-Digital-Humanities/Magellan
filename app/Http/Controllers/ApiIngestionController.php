<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Validator;
use Hash;
use Illuminate\Validation\Rule;
use App\Entry;
use App\User;
use App\Topic;
use App\EntryTopic;
use App\Helpers;
use App\Uploadedfile;
use App\UserTranscription;
use App\Rights;
use DB;
use App\Pages as Pages;
use App\EntryFormats as EntryFormats;
use Illuminate\Http\Request;
use App\Http\Controllers\FileEntryController;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;


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

    public function indexAll(Request $request)
    {

        if (Entry::first() != null) {
            $sort = "asc";
            if ($request->input('sort') !== "") {
                $sort = $request->input('sort');
            }
            $coll = Entry::where('current_version', 1)->orderBy('created_at', $sort)->get();
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
            $file_id = $entry->uploadedfile_id;
            if ($file_id!==null) {
              $file = Uploadedfile::select('id', 'filename', 'original_filename')
                  ->where('id',$file_id)->get();
              $link = asset('/').'download-xml/'.$file_id;
              //$link = asset('/').'download-xml/'.$file[0]['filename'];
              $newFile = $file[0];
              $newFile['link'] = $link;
              $coll["file"] = $newFile;
            }

        } else {
            $coll = "";
            $msg = "Entry not found";
            $status = false;

            abort(404, json_encode($this->prepareResult($status, $coll, [], $msg, "Request failed")));
        };

        return $this->prepareResult($status, $coll, [], $msg, "Request complete");

    }

    public function showLetter(Request $request, $id)
    {
        $entry = DB::table('entry')
          ->join('uploadedfile', 'entry.uploadedfile_id','=','uploadedfile.id')
          ->select('entry.*')
          ->where('uploadedfile.original_filename',$id.".xml")
          ->where('entry.current_version','1')
          ->first();

        $msg = "Entry found";
        $status = true;


        if ($entry != null) {
            $coll = json_decode($entry->element, true);
            $file_id = $entry->uploadedfile_id;
            if ($file_id!==null) {
              $file = Uploadedfile::select('id', 'filename', 'original_filename')
                  ->where('id',$file_id)->get();
              $link = asset('/').'download-xml/'.$file_id;
              $newFile = $file[0];
              $newFile['link'] = $link;
              $coll["file"] = $newFile;
            }

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
        $format = $request->input('format');
        $data = $request->input('data');

        $entry_format = EntryFormats\Factory::create($format);
        $data_json = json_decode($data,true);

        $validator = $entry_format->valid($data_json);

        if ($validator->fails()) {

            $error = true;
            $errors['document_id'] = $data_json['document_id'];
            $errors['errors'] = $validator->errors();

            return $this->prepareResult(false, $errors, $error['errors'], "Error in creating entry");

        } else {
            $document_id = intval($data_json['document_id']);
            $entryPages = $data_json['pages'];
            $transcription_status = $data_json['transcription_status'];

            // check if the entry already exists in the db
            $existing_entry = Entry::where('element->document_id', intval($document_id))->first();
            $existing_element = json_decode($existing_entry['element'], true);
            $existing_modified_timestamp = $existing_element['modified_timestamp'];

            if ($existing_entry!==null && $existing_modified_timestamp===$data_json['modified_timestamp']) {
              return $this->prepareResult(true, [], $error['errors'], "Entry already inserted");
            }
            else {
              $current_version = 1;

              if ($existing_entry!==null && $existing_entry->current_version===1) {
                $current_version = 0;
              }
              $entry = new Entry();
              $entry->element = json_encode($data_json);
              $entry->user_id = Auth::user()->id;
              $entry->current_version = $current_version;
              $entry->status = $this->getEntryStatus($entryPages);
              $entry->transcription_status = $transcription_status;
              $entry->notes = "";

              $entry->save();

              return $this->prepareResult(true, [], $error['errors'], "Entry created");
            }

        }

    }

    public function testAPI() {

        $new_items = array();
        $items = Entry::where([
            ['current_version','=', 1],
            ['status','=', 0],
            ['transcription_status','=', 0],
            ])
            ->orderBy('id')
            ->get();
        foreach($items as $item) {
          $element = json_decode($item->element, true);
          $completed = 0;
          $pages = $element['pages'];
          foreach($pages as $page) {
            $tstatus = $page['transcription_status'];
            if ($tstatus>0) {
              $completed++;
            }
          }
          $percentage = 0;
          if ($completed>0) {
            $percentage = ($completed/count($pages))*100;
          }
          $item['completed']=$percentage;
          $new_items[]=$item;
        }

        $return_items = array();
        foreach($new_items as $key=>$row) {
          $return_items[$key]=$row['completed'];
        }

        array_multisort($return_items, SORT_ASC, $new_items);
        $collection = collect($new_items);

      return $this->prepareResult(true, $collection->forPage(1,10), [], "All user entries");
    }

    public function uploadLetter(Request $request,$id) {
      $postData = $request->all();
      $formData = json_decode($postData['form']);
      $now = date("Y-m-d\TH:i:sP");
      $images = array();
      if (isset($postData['data'])) {
        $images = $postData['data'];
      }
      $images_types = $formData->additional_img_info;

      // topics
      $topics = array();
      $keywords = $formData->keywords;
      foreach ($keywords as $keyword) {
        $topic = array(
          "topic_id"=>$keyword->value,
          "topic_name"=>$keyword->label
        );
        $topics[] = $topic;
      }

      // date
      $date_created = (string)$formData->year;
      if (isset($formData->month) && $formData->month!=="") {
        $date_created .= "-".$formData->month;
      }
      if (isset($formData->day) && $formData->day!=="") {
        $date_created .= "-".$formData->day;
      }
      // source
      $source = "";
      if (gettype($formData->source)==="object") {
        $source = $formData->source->value;
      }
      else {
        $source = $formData->source;
      }
      // letter from
      $letter_from = "";
      if (gettype($formData->letter_from)==="object") {
        $letter_from = $formData->letter_from->value;
      }
      else {
        $letter_from = $formData->letter_from;
      }
      // letter to
      $letter_to = "";
      if (gettype($formData->letter_to)==="object") {
        $letter_to = $formData->letter_to->value;
      }
      else {
        $letter_to = $formData->letter_to;
      }

      $imgs_errors = array();
      if (intval($id)===0) {
        // pages
        $pages = array();
        $i=0;
        // uploaded files
        foreach($images as $image) {
          // upload image and store
          $fileEntryController = new FileEntryController();
          if ($fileEntryController->isImage($image)) {
            $extension=$image->getClientOriginalExtension();
            $filename = $image->getFilename().'.'.$extension;
            Storage::disk('fullsize')->put($filename, File::get($image));
            $fileEntryController->makeThumbnail($filename, 200);
            $saved_file = $fileEntryController->store($image, "uploader page", Auth::user()->id);
            $count = $i+1;

            $image_type = "Letter";
            if(isset($images_types[$i])) {
              $image_type = $images_types[$i];
            }
            $page = array(
              // logged in user id
              "rev_id"=>Auth::user()->id,
    		      "page_id"=> "",
              // logged in user name
    		      "rev_name"=> Auth::user()->name,
    		      "page_type"=> $image_type,
    		      "page_count"=> $count,
    		      "contributor"=> "",
    		      "transcription"=> "",
          		"archive_filename"=> $saved_file->filename,
          		"original_filename"=> $image->getClientOriginalName(),
          		"last_rev_timestamp"=> $now,
          		"transcription_status"=> "0",
          		"doc_collection_identifier"=> ""
            );
            $pages[]=$page;
            $i++;
          }
          else {
            $imgs_errors[] = array("filename"=>$image->getClientOriginalName());
          }
        }

        $count=1;
        $newPages = array();
        foreach($pages as $page) {
          $page['page_count'] = $count;
          $newPages[]=$page;
          $count++;
        }

        $json_element = array(
          "type"=>"uploader",
  	      "debug"=>"",
  	      "pages"=> $newPages,
          "title"=> $formData->title,
        	"source"=> $source,
          "topics"=> $topics,
          "creator"=> $letter_from,
        	"creator_gender"=> $formData->creator_gender,
          "creator_location"=>$formData->creator_location,
          "user_id"=> Auth::user()->id,
          "language" => $formData->language,
          "recipient" => $letter_to,
          "time_zone"=> "Europe/Dublin",
          "collection"=> "",
          "api_version"=> "1.0",
          "description"=> $formData->additional_information,
          "document_id"=> $postData['letter_id'],
          "date_created"=>$date_created,
          "number_pages"=>count($images),
          "request_time"=>$now,
          "terms_of_use"=>$formData->terms_of_use,
          "collection_id"=>"",
          "doc_collection"=>$formData->doc_collection,
          "modified_timestamp"=>$now,
          "recipient_location"=>$formData->recipient_location,
          "copyright_statement"=>$formData->copyright_statement,
          "year_of_death_of_author"=>$formData->year_of_death_of_author,
        );
        $error = false;
        $format = "uploader";
        $entry_format = EntryFormats\Factory::create($format);
        $validator = $entry_format->valid($json_element);
        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();

            return $this->prepareResult(false, [$errors], $error['errors'], "Error in creating entry");
        }
        else {
            $entry = new Entry();
            $entry->element = json_encode($json_element);
            $entry->user_id = Auth::user()->id;
            $entry->current_version = TRUE;
            $entry->status = 0;
            $entry->transcription_status = 0;
            $entry->notes = $formData->notes;
            $entry->save();
            $error['files']=$imgs_errors;

            return $this->prepareResult(true, $entry, $error, "Entry created");
        }

      }
      else if (intval($id)>0) {
        // pages
        $pages = array();
        if (count($formData->pages)>0) {
          foreach($formData->pages as $newPage) {
            $newPage = (array)$newPage;
            $newPage['page_id']=0;
            $pages[] = $newPage;
          }
        }

        // uploaded files
        $i=0;
        foreach($images as $image) {
          // upload image and store

          $fileEntryController = new FileEntryController();
          if ($fileEntryController->isImage($image)) {
            $extension=$image->getClientOriginalExtension();
            $filename = $image->getFilename().'.'.$extension;
            Storage::disk('fullsize')->put($filename, File::get($image));
            $fileEntryController->makeThumbnail($filename, 200);
            $saved_file = $fileEntryController->store($image, "uploader page", Auth::user()->id);


            $image_type = "Letter";
            if(isset($images_types[$i])) {
              $image_type = $images_types[$i];
            }
            $page = array(
              // logged in user id
              "rev_id"=>Auth::user()->id,
    		      "page_id"=> "",
              // logged in user name
    		      "rev_name"=> Auth::user()->name,
    		      "page_type"=> $image_type,
    		      "contributor"=> "",
    		      "transcription"=> "",
          		"archive_filename"=> $saved_file->filename,
          		"original_filename"=> $image->getClientOriginalName(),
          		"last_rev_timestamp"=> $now,
          		"transcription_status"=> "0",
          		"doc_collection_identifier"=> ""
            );
            $pages[]=$page;
            $i++;
          }
          else {
            $imgs_errors[] = array("filename"=>$image->getClientOriginalName());
          }
        }
        $count=1;
        $newPages = array();
        foreach($pages as $page) {
          $page['page_count'] = $count;
          $newPages[]=$page;
          $count++;
        }

        $entry = Entry::where('id',$id)->first();
        $decode = json_decode($entry->element, true);
        $entry_type = $decode['type'];
        // element
        $json_element = array(
          "type"=>$entry_type,
  	      "debug"=>"",
  	      "pages"=> $newPages,
          "title"=> $formData->title,
        	"source"=> $source,
          "topics"=> $topics,
          "creator"=> $letter_from,
        	"creator_gender"=> $formData->creator_gender,
          "creator_location"=>$formData->creator_location,
          "user_id"=> Auth::user()->id,
          "language" => $formData->language,
          "recipient" => $letter_to,
          "time_zone"=> "Europe/Dublin",
          "collection"=> "",
          "api_version"=> "1.0",
          "description"=> $formData->additional_information,
          "document_id"=> $postData['letter_id'],
          "date_created"=>$date_created,
          "number_pages"=>$i,
          "request_time"=>$now,
          "terms_of_use"=>$formData->terms_of_use,
          "collection_id"=>"",
          "doc_collection"=>$formData->doc_collection,
          "modified_timestamp"=>$now,
          "recipient_location"=>$formData->recipient_location,
          "copyright_statement"=>$formData->copyright_statement,
          "year_of_death_of_author"=>$formData->year_of_death_of_author,
        );

        $error = false;
        $format = "uploader";
        $entry_format = EntryFormats\Factory::create($format);
        $validator = $entry_format->valid($json_element);
        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();

            return $this->prepareResult(false, $errors, $errors, "Error in updating entry");
        }
        else {

          $entry->element = json_encode($json_element);
          $entry->notes = $formData->notes;
          $entry->save();

          $json_element['notes'] = $formData->notes;
          $error['files']=$imgs_errors;
          return $this->prepareResult(true, $json_element, $error, "Entry updated successfully");
        }
      }

    }

    public function updatePagesOrder(Request $request, $id) {
      $error = array();
      $pages = (array)$request->json('pages');
      $entry = Entry::where('id', $id)->first();
      $element = json_decode($entry->element, true);

      $count=1;
      $newPages = array();
      foreach($pages as $page) {
        $page['page_count'] = $count;
        $newPages[]=$page;
        $count++;
      }

      $element['pages'] = $newPages;
      Entry::whereId($id)->update(['element'=>json_encode($element)]);

      return $this->prepareResult(true, $pages, $error, "Pages order updated successfully");
    }

    public function letterTranscribe(Request $request, $id)
    {
        $msg = "Entry found";
        $status = true;
        // associate transcription with user
        Auth::user()->transcriptions()->sync([$id],false);

        // load entry
        $entry = Entry::where('id', $id)->first();
        if ($entry != null) {
          $coll = json_decode($entry->element, true);
          // handle entry lock
          if (!$entry->handleEntryLock()) {
            $status = false;
            return $this->prepareResult($status, $coll, [], "This entry is in use by another user!", "Request complete");
          }
        }
        else {
          $coll = "";
          $msg = "Entry not found";
          $status = false;

          abort(404, json_encode($this->prepareResult($status, $coll, [], $msg, "Request failed")));
        };

        return $this->prepareResult($status, $coll, [], $msg, "Request complete");

    }


    public function updateTranscriptionPage(Request $request, $id){
      $error = "";
      $entry = Entry::where('id', $id)->first();
      // handle entry lock
      if (!$entry->handleEntryLock()) {
        $status = false;
        return $this->prepareResult($status, [], [], "This entry is in use by another user!", "Request complete");
      }
      if ($entry->transcription_status!==0) {
        $error = "This item cannot be transcribed";
        return $this->prepareResult(true, $id, $error, "Page transcription error");
      }
      $archive_filename = $request->archive_filename;
      $transcription = $request->transcription;
      $element = json_decode($entry->element, true);
      $pages = $element['pages'];
      $newPages = array();
      foreach($pages as $page) {
        if ($page['archive_filename']===$archive_filename) {
          $page['transcription'] = addslashes($transcription);
        }
        $newPages[]=$page;
      }
      $element['pages']=$newPages;
      Entry::whereId($id)->update(['element'=>json_encode($element)]);
      return $this->prepareResult(true, $newPages, $error, "Page transcription updated successfully");
    }

    public function deleteLetterPage(Request $request) {
      $error = array();
      //print_r($request->pages);
      $id = intval($request->json('id'));
      $archive_filename = $request->json('archive_filename');
      $entry = Entry::where('id', $id)->first();
      $element = json_decode($entry->element, true);
      $pages = $element['pages'];
      $newPages = array();
      $i=1;
      foreach($pages as $page) {
        if ($page['archive_filename']!==$archive_filename) {
          $page['page_count']=$i;
          $newPages[]=$page;
          $i++;
        }
      }
      // delete file from storage
      Storage::disk('fullsize')->delete($archive_filename);
      Storage::disk('thumbnails')->delete($archive_filename);
      $element['pages'] = $newPages;
      Entry::whereId($id)->update(['element'=>json_encode($element)]);
      Uploadedfile::where("filename",$archive_filename)->delete();

      return $this->prepareResult(true, $newPages, $error, "Page deleted successfully");
    }

    public function deleteLetter(Request $request) {
      $error = array();
      //print_r($request->pages);
      $id = intval($request->json('id'));
      $entry = Entry::where('id', $id)->first();
      $response = $entry->deleteEntry($entry, Auth::user()->id);

      return $this->prepareResult(true, $response, $error, "Letter deleted successfully");
    }

    public function removeTranscriptionAssociation(Request $request) {
      $id = $request->input('id');
      if ($id>0) {
        Auth::user()->transcriptions()->detach($id);
      }
      return $this->prepareResult(true, [], [], "User transcriptions removed succesfully");
    }


    public function fullsearch(Request $request, $sentence)
    {

        $sanitize_sentence = filter_var($sentence, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

        $match_sql = "match(`pages`.`title`,`pages`.`description`,`pages`.`text_body`) against ('$sanitize_sentence' in boolean mode)";

        $paginate = 10;
        if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
            $paginate = $request->input('paginate');
        }

        $pages = Pages::select('entry_id', 'title', 'description', DB::raw(($match_sql) . "as score"), 'page_number')
          ->join('entry', 'entry.id','=','pages.entry_id')
          ->with('entry')
          ->where('entry.current_version','=','1')
          ->where('pages.transcription_status','=','2')
          ->whereRaw($match_sql)
          ->orderBy('score', 'desc')
          ->paginate($paginate);
        return $this->prepareResult(true, $pages, $sanitize_sentence, "Results created");

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


    /**
     * Get a validator for an incoming ApiIngestion request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  $type
     * @return \Illuminate\Contracts\Validation\Validator
     */


    public function sources() {

      $sources = Entry::select(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) as source, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->groupBy(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->orderBy(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
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
      $status = null;
      $transcription_status = null;
      $keywords_ids = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $date_start = null;
      $date_end = null;

      if ($request->input('sort')!=="") {
        $sort = $request->input('sort');
      }
      if ($request->input('paginate')!=="") {
        $paginate = $request->input('paginate');
      }
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
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
      if ($request->input('date_start')) {
        $date_start = $request->input('date_start');
      }
      if ($request->input('date_end')) {
        $date_end = $request->input('date_end');
      }

      if ($status===null || $transcription_status===null) {
        return $this->prepareResult(true, [], [], "Please set the status and transcription status.");
      }

      $entry_ids = array();
      // keywords
      if (count($keywords_ids)>0) {
        $keywords_ids_num = count($keywords_ids);
        $keywords_entry_ids = EntryTopic::select('entry_id', DB::raw('COUNT(entry_id) as c'))
        ->whereIn('topic_id',$keywords_ids)
        ->groupBy('entry_id')
        ->havingRaw('c='.$keywords_ids_num)
        ->get();
        $keywords_entry_ids = $keywords_entry_ids->toArray();
        $new_keywords_entry_ids = array();
        foreach($keywords_entry_ids as $keywords_entry_id) {
          $new_keywords_entry_ids[]=$keywords_entry_id['entry_id'];
        }
        $entry_ids[] = $new_keywords_entry_ids;
      }
      // sources
      if (count($sources)>0) {
        $new_sources = $this->inputArraytoString($sources);
        $sources_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) IN (".$new_sources.")"))
          ->get();
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
        $authors_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))) IN (".$new_authors.")"))
          ->get();
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
        $genders_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))) IN (".$new_genders.")"))
          ->get();
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
        $languages_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))) IN (".$new_languages.")"))
          ->get();
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
      if ($date_start!==null || $date_end!==null) {
        if ($date_start!==null && $date_end===null) {
          $date_end = $date_start;
        }
        $date_start = date($date_start);
        $date_end = date($date_end);
        $date_created_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereBetween(DB::raw("CAST(TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))) AS DATE)"), [$date_start, $date_end])
          ->get();
        if(count($date_created_ids)>0) {
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
        else $entry_ids[] = [];

      }

      if (count($entry_ids)>0) {
          $coll = Entry::whereIn('id',$entry_ids[0])
            ->where([
              ['current_version','=', 1],
              ['status','=', $status],
              ['transcription_status','=', $transcription_status],
              ])
            ->orderBy('created_at', $sort)
            ->paginate($paginate);
      } else {
        if (Entry::first() != null) {
          $coll = Entry::where([
            ['current_version','=', 1],
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
            ->orderBy('created_at', $sort)
            ->paginate($paginate);
        } else {
            $coll = "empty bottle";
        };
      };

      return $this->prepareResult(true, $coll, [], "All user entries");
    }


    public function transcriptionsDeskfiltered(Request $request)
    {
      $sort = "asc";
      $page = 1;
      $paginate = 10;
      $status = null;
      $transcription_status = null;
      $keywords_ids = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $date_start = null;
      $date_end = null;

      if ($request->input('sort')!=="") {
        $sort = $request->input('sort');
      }
      if ($request->input('page')!=="") {
        $page = $request->input('page');
      }
      if ($request->input('paginate')!=="") {
        $paginate = $request->input('paginate');
      }
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
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
      if ($request->input('date_start')) {
        $date_start = $request->input('date_start');
      }
      if ($request->input('date_end')) {
        $date_end = $request->input('date_end');
      }

      if ($status===null || $transcription_status===null) {
        return $this->prepareResult(true, [], [], "Please set the status and transcription status.");
      }

      $entry_ids = array();
      // keywords
      if (count($keywords_ids)>0) {
        $keywords_ids_num = count($keywords_ids);
        $keywords_entry_ids = EntryTopic::select('entry_id', DB::raw('COUNT(entry_id) as c'))
        ->whereIn('topic_id',$keywords_ids)
        ->groupBy('entry_id')
        ->havingRaw('c='.$keywords_ids_num)
        ->get();
        $keywords_entry_ids = $keywords_entry_ids->toArray();
        $new_keywords_entry_ids = array();
        foreach($keywords_entry_ids as $keywords_entry_id) {
          $new_keywords_entry_ids[]=$keywords_entry_id['entry_id'];
        }
        $entry_ids[] = $new_keywords_entry_ids;
      }
      // sources
      if (count($sources)>0) {
        $new_sources = $this->inputArraytoString($sources);
        $sources_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) IN (".$new_sources.")"))
          ->get();
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
        $authors_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))) IN (".$new_authors.")"))
          ->get();
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
        $genders_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))) IN (".$new_genders.")"))
          ->get();
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
        $languages_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))) IN (".$new_languages.")"))
          ->get();
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
      if ($date_start!==null || $date_end!==null) {
        if ($date_start!==null && $date_end===null) {
          $date_end = $date_start;
        }
        $date_start = date($date_start);
        $date_end = date($date_end);
        $date_created_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereBetween(DB::raw("CAST(TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))) AS DATE)"), [$date_start, $date_end])
          ->get();
        if(count($date_created_ids)>0) {
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
        else $entry_ids[] = [];

      }

      if (count($entry_ids)>0) {
          $items = Entry::whereIn('id',$entry_ids[0])
            ->where([
              ['current_version','=', 1],
              ['status','=', $status],
              ['transcription_status','=', $transcription_status],
              ])
            ->get();



      } else {
        if (Entry::first() != null) {
          $items = Entry::where([
            ['current_version','=', 1],
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
            ->get();

        } else {
            $coll = "empty bottle";
        };
      };
      $new_items = array();
      foreach($items as $item) {
        $element = json_decode($item->element, true);
        $completed = 0;
        $element_pages = $element['pages'];
        foreach($element_pages as $element_page) {
          $tstatus = $element_page['transcription_status'];
          if ($tstatus>0) {
            $completed++;
          }
        }
        $percentage = 0;
        if ($completed>0) {
          $percentage = ($completed/count($element_pages))*100;
        }
        $item['completed']=$percentage;
        $new_items[]=$item;
      }

      $return_items = array();
      foreach($new_items as $key=>$row) {
        $return_items[$key]=$row['completed'];
      }

      if ($sort==="asc") {
        array_multisort($return_items, SORT_ASC, $new_items);
      }
      if ($sort==="desc") {
        array_multisort($return_items, SORT_DESC, $new_items);
      }

      $count = count($new_items);
      $coll = collect($new_items)->forPage(intval($page), intval($paginate))->values();
      $paginator = new Paginator($coll, $count, $paginate, $page, [
        'path'  => Paginator::resolveCurrentPath()
      ]);
      return $this->prepareResult(true, $paginator, [], "All user entries");
    }

    public function indexfilteredFilters(Request $request)
    {
      $sort = "asc";
      $status = null;
      $transcription_status = null;
      $keywords_ids = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $date_start = null;
      $date_end = null;

      if ($request->input('sort')!=="") {
        $sort = $request->input('sort');
      }
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
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
      if ($request->input('date_start')) {
        $date_start = $request->input('date_start');
      }
      if ($request->input('date_end')) {
        $date_end = $request->input('date_end');
      }

      if ($status===null || $transcription_status===null) {
        return $this->prepareResult(true, [], [], "Please set the status and transcription status.");
      }

      $entry_ids = array();
      // keywords
      if (count($keywords_ids)>0) {
        $keywords_ids_num = count($keywords_ids);
        $keywords_entry_ids = EntryTopic::select('entry_id', DB::raw('COUNT(entry_id) as c'))
        ->whereIn('topic_id',$keywords_ids)
        ->groupBy('entry_id')
        ->havingRaw('c='.$keywords_ids_num)
        ->get();
        $keywords_entry_ids = $keywords_entry_ids->toArray();
        $new_keywords_entry_ids = array();
        foreach($keywords_entry_ids as $keywords_entry_id) {
          $new_keywords_entry_ids[]=$keywords_entry_id['entry_id'];
        }
        $entry_ids[] = $new_keywords_entry_ids;
      }

      /// sources
      if (count($sources)>0) {
        $new_sources = $this->inputArraytoString($sources);
        $sources_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) IN (".$new_sources.")"))
          ->get();
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
        $authors_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))) IN (".$new_authors.")"))
          ->get();
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
        $genders_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))) IN (".$new_genders.")"))
          ->get();
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
        $languages_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereRaw(DB::raw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))) IN (".$new_languages.")"))
          ->get();
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
      if ($date_start!==null || $date_end!==null) {
        if ($date_start!==null && $date_end===null) {
          $date_end = $date_start;
        }
        $date_start = date($date_start);
        $date_end = date($date_end);
        $date_created_ids = Entry::select("id")
          ->where([
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->whereBetween(DB::raw("CAST(TRIM(JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))) AS DATE)"), [$date_start, $date_end])
          ->get();
        if(count($date_created_ids)>0) {
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
        else $entry_ids[] = [];
      }

      if (count($entry_ids)>0) {
          $coll = Entry::whereIn('id',$entry_ids[0])
            ->where([
              ['current_version','=', 1],
              ['status','=', $status],
              ['transcription_status','=', $transcription_status],
              ])
            ->orderBy('created_at', $sort)
            ->get();
      } else {
        if (Entry::first() != null) {
          $coll = Entry::where([
            ['current_version','=', 1],
            ['status','=', $status],
            ['transcription_status','=', $transcription_status],
            ])
          ->orderBy('created_at', $sort)
          ->get();
        } else {
            $coll = "empty bottle";
        };
      };

      //dd($entry_ids);
      $keywords = array();
      $sources = array();
      $authors = array();
      $genders = array();
      $languages = array();
      $dates_sent = array();

      for ($i=0;$i<count($coll); $i++) {
        $coll_decode = json_decode($coll[$i]["element"], true);
        // keywords
        if (isset($coll_decode["topics"])) {
          $coll_keywords = $coll_decode["topics"];
          for ($k=0;$k<count($coll_keywords);$k++) {
            $coll_keyword = $coll_keywords[$k]['topic_name'];
            $keywords[]=$coll_keyword;
          }
        }

        //sources
        if (isset($coll_decode["source"])) {
          $coll_source = $coll_decode["source"];
          $sources[]=$coll_source;
        }
        //authors
        if (isset($coll_decode["creator"])) {
          $coll_author = $coll_decode["creator"];
          $authors[]=$coll_author;
        }

        //gender
        if (isset($coll_decode["creator_gender"])) {
          $coll_gender = $coll_decode["creator_gender"];
          $genders[]=$coll_gender;
        }
        //language
        if (isset($coll_decode["language"])) {
          $coll_language = $coll_decode["language"];
          $languages[]=$coll_language;
        }
        //dates_sent
        if (isset($coll_decode["date_created"])) {
          $coll_date_sent = $coll_decode["date_created"];
          $dates_sent[]=$coll_date_sent;
        }
      }
      //dd($keywords);
      $data = array(
        "keywords"=> array_count_values($keywords),
        "sources"=> array_count_values($sources),
        "authors"=> array_count_values($authors),
        "genders"=> array_count_values($genders),
        "languages"=> array_count_values($languages),
        "dates_sent"=> array_count_values($dates_sent),
      );

      return $this->prepareResult(true, $data, [], "All user entries");
    }

    public function rights(Request $request) {
      $rights = Rights::where("status",1)->get();
      return $this->prepareResult(true, $rights, [], "All available rights");
    }
}
