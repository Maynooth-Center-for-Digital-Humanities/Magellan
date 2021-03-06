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
        $user = null;
        if ( $request->has('Authorization') || $request->header('Authorization') ) {
            $user = Auth::guard('api')->user();
        }
        if ($user!==null){
          if ($user->isAdmin()) {
            $entry = Entry::where('id', $id)->first();
          }
          else {
            $entry = Entry::where([
              ['id','=', $id],
              ['current_version','=',1],
              ['status','=',1],
              ['transcription_status','=',2],
            ])->first();
          }
        }
        else {
          $entry = Entry::where([
            ['id','=', $id],
            ['current_version','=',1],
            ['status','=',1],
            ['transcription_status','=',2],
          ])->first();
        }
        $msg = "Entry found";
        $status = true;


        if ($entry !== null) {
            $coll = json_decode($entry->element, true);
        } else {
            $coll = "";
            $msg = "Entry not found";
            $status = false;

            return $this->prepareResult($status, $coll, [], $msg, "Request complete");

        };

        return $this->prepareResult($status, $coll, [], $msg, "Request complete");

    }

    public function showLetter(Request $request, $id)
    {
        $user = null;
        if ( $request->has('Authorization') || $request->header('Authorization') ) {
            $user = Auth::guard('api')->user();
        }
        if ($user!==null){
          if ($user->isAdmin()) {
            $entry = DB::table('entry')
              ->join('uploadedfile', 'entry.uploadedfile_id','=','uploadedfile.id')
              ->select('entry.*')
              ->where('uploadedfile.original_filename',$id.".xml")
              ->where('entry.current_version','1')
              ->first();
          }
          else {
            $entry = DB::table('entry')
              ->join('uploadedfile', 'entry.uploadedfile_id','=','uploadedfile.id')
              ->select('entry.*')
              ->where([
                ['uploadedfile.original_filename',$id.".xml"],
                ['current_version','=',1],
                ['status','=',1],
                ['transcription_status','=',2]
              ]
                )
              ->where('entry.current_version','1')
              ->first();
          }
        }
        else {
          $entry = DB::table('entry')
            ->join('uploadedfile', 'entry.uploadedfile_id','=','uploadedfile.id')
            ->select('entry.*')
            ->where([
              ['uploadedfile.original_filename',$id.".xml"],
              ['current_version','=',1],
              ['status','=',1],
              ['transcription_status','=',2]
            ]
              )
            ->where('entry.current_version','1')
            ->first();
        }



        $msg = "Entry found";
        $status = true;


        if ($entry != null) {
            $coll = json_decode($entry->element, true);

        } else {
            $coll = "";
            $msg = "Entry not found";
            $status = false;

            return $this->prepareResult($status, $coll, [], $msg, "Request complete");
        };

        return $this->prepareResult($status, $coll, [], $msg, "Request complete");

    }

    public function showLetters(Request $request, $page=0, $limit=500)
    {
        $skip = intval($page)*intval($limit);

        $entries = DB::table('entry')
          ->select('entry.element')
          ->where('current_version','1')
          ->skip($skip)
          ->take($limit)
          ->get();


        $msg = "Entries found";
        $status = true;

        return $this->prepareResult($status, $entries, [], $msg, "Request complete");

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

        }
        else {
            $document_id = intval($data_json['document_id']);
            $entryPages = $data_json['pages'];
            $transcription_status = intval($data_json['transcription_status']);
            $status = intval($data_json['status']);
            if ($transcription_status===0 && $status===0) {
              $transcription_status = -1;
            }

            $existing_entry = null;
            if ($document_id>0) {

              // check if the entry already exists in the db
              $existing_entry = Entry::where('element->document_id', intval($document_id))->first();
              $existing_element = json_decode($existing_entry['element'], true);
              $existing_modified_timestamp = $existing_element['modified_timestamp'];
            }
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

              // update entry with document id
              if ($document_id>0) {
                $newId = $document_id;
              }
              else {
                $newId = $entry->id;
              }
              $newEntry = Entry::find($newId);
              $newElement = $data_json;
              $newElement['document_id'] = intval($newId);

              $newEntry->element = json_encode($newElement);
              $newEntry->save();

              return $this->prepareResult(true, [], $error['errors'], "Entry created");
            }

        }

    }

    public function missedFilesPatch(Request $request)
    {

        $error = false;
        $format = "uploader";
        $data = $request->input('data');

        $entry_format = EntryFormats\Factory::create($format);
        $data_json = json_decode($data,true);
        $document_id = intval($data_json['document_id']);

        $validator = $entry_format->valid($data_json);

        if ($validator->fails()) {

            $error = true;
            $errors['document_id'] = $data_json['document_id'];
            $errors['errors'] = $validator->errors();

            return $this->prepareResult(false, $errors, $error['errors'], "Error in creating entry");
        }
        else {
            $response = array();
            $document_id = intval($data_json['document_id']);
            $entryPages = $data_json['pages'];
            $transcription_status = intval($data_json['transcription_status']);
            $status = intval($data_json['status']);
            if ($transcription_status===0 && $status===0) {
              $transcription_status = -1;
            }

            // check if the entry already exists in the db and update if pages are empty
            $existing_entries = Entry::where('element->document_id', intval($document_id))->get();
            if (count($existing_entries)>0) {
              foreach ($existing_entries as $existing_entry) {
                $existing_element = json_decode($existing_entry['element'], true);
                $existing_pages = $existing_element['pages'];
                if (empty($existing_pages)) {
                  $existing_element['pages']=$entryPages;
                  $existing_entry->element=json_encode($existing_element);
                  $existing_entry->save();

                  $response[] = $existing_entry->id." pages updated successfully";
                }
              }
            }
            else {
              $current_version = 1;

              $entry = new Entry();
              $entry->element = json_encode($data_json);
              $entry->user_id = Auth::user()->id;
              $entry->current_version = $current_version;
              $entry->status = $this->getEntryStatus($entryPages);
              $entry->transcription_status = $transcription_status;
              $entry->notes = "";

              $entry->save();

              $response[] = "New entry for Omeka document with id:".$document_id." created successfully";
            }
            return $this->prepareResult(true, $response, $error['errors'], $document_id);
        }

    }

    public function testAPI(Request $request) {
      $status = null;
      $transcription_status = null;
      $current_version = null;
      $paginate = 100;
      $page = 1;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      if ($request->input('current_version')!=="") {
        $current_version = $request->input('current_version');
      }
      if ($request->input('paginate')!=="") {
        $paginate = $request->input('paginate');
      }
      if ($request->input('page')!=="") {
        $page = $request->input('page');
      }

      $whereQuery = array();
      if ($status!==null) {
        $whereQuery[] = array("status", "=", $status);
      }
      if ($transcription_status!==null) {
        $whereQuery[] = array("transcription_status", "=", $transcription_status);
      }
      if ($current_version!==null) {
        $whereQuery[] = array("current_version", "=", $current_version);
      }

      $coll = Entry::where($whereQuery)
        ->orderBy('id')
        ->paginate($paginate, ['*'], 'page',$page);

      return $this->prepareResult(true, $coll, [], "");
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
            $filename = $image->getFilename().'.'.strtolower($extension);
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
          		"transcription_status"=> "-1",
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
            $entry->transcription_status = -1;
            $entry->notes = $formData->notes;
            $entry->save();
            $error['files']=$imgs_errors;

            // update entry with document id
            $newId = $entry->id;
            $newEntry = Entry::find($newId);
            $newElement = $json_element;
            $newElement['document_id'] = intval($newId);

            $newEntry->element = json_encode($newElement);
            $newEntry->save();

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

        // load entry
        $entry = Entry::where([
            ['id', '=', $id],
            ['transcription_status','>',-1],
            ['status','=',0]
          ])->first();
        if ($entry != null) {

          // associate transcription with user
          Auth::user()->transcriptions()->sync([$id],false);

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
          return $this->prepareResult($status, $coll, [], $msg, "Request failed");
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

      return $this->prepareResult($response['status'], [], $response['error'], $response['msg']);

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
          ->where([
            ['entry.current_version','=',1],
            ['entry.status','=',1],
            ['entry.transcription_status','=',2],
            ['pages.transcription_status','=',2]
            ])
          ->whereRaw($match_sql)
          ->orderBy('score', 'desc')
          ->paginate($paginate);
        return $this->prepareResult(true, $pages, $sanitize_sentence, "Results created");

    }

    public function search(Request $request, $sentence) {
        $sort_col = "element->title";
        $sort_dir = "desc";
        $status = 1;
        $transcription_status = 2;
        $paginate = 10;
        $page = 1;
        if ($request->input('status') !== null && $request->input('status') !== "") {
            $status = $request->input('status');
        }
        if ($request->input('transcription_status') !== null && $request->input('transcription_status') !== "") {
            $transcription_status = $request->input('transcription_status');
        }
        if ($request->input('sort_col') !== null && $request->input('sort_col') !== "") {
            $sort_col = $request->input('sort_col');
        }
        if ($request->input('sort_dir') !== null && $request->input('sort_dir') !== "") {
            $sort_dir = $request->input('sort_dir');
        }
        if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
            $paginate = $request->input('paginate');
        }
        if ($request->input('page') !== null && $request->input('page') !== "") {
            $page = $request->input('page');
        }

        $sanitize_sentence = urldecode(strtolower($sentence));

        $where_q = [
          ['status','=',1],
          ['current_version','=',1],
          ['transcription_status','=',2],
        ];

        $entries = Entry::select('entry.*')
          ->where($where_q)
          ->where(function($q) use($sanitize_sentence) {
              $q
                ->whereRaw("LOWER(`element`->>'$.title') like ?", '%'.$sanitize_sentence.'%')
                ->orWhereRaw("LOWER(`element`->>'$.description') like ?",'%'.$sanitize_sentence.'%')
                ->orWhereRaw("LOWER(`fulltext`) like ?",'%'.$sanitize_sentence.'%');
          })
          ->groupBy('id')
          ->orderBy($sort_col, $sort_dir)
          ->paginate($paginate);

        return $this->prepareResult(true, $entries, [], "Results created");

    }

    public function viewtopics(Request $request, $expr = "")
    {
      if(empty($expr)) {
          $status = null;
          $transcription_status = null;
          if ($request->input('status')!=="") {
            $status = $request->input('status');
          }
          if ($request->input('transcription_status')!=="") {
            $transcription_status = $request->input('transcription_status');
          }
          $where_q = [
            ['entry.current_version','=',1]
          ];
          if ($status!==null) {
            $where_q[] = ['entry.status','=', $status];
          }
          if ($transcription_status!==null) {
            $where_q[] = ['entry.transcription_status','=', $transcription_status];
          }
          $results = array();
          $topic_ids = array();
          $topic_ids_results = EntryTopic::select('entry_topic.topic_id')
            ->join('entry', 'entry.id','=','entry_topic.entry_id')
            ->where($where_q)
            ->get();
          foreach($topic_ids_results as $topic_ids_result) {
            $topic_ids[]=$topic_ids_result['topic_id'];
          }
          $rootTopics = Topic::select('id', 'name', 'count')
            ->whereIn('id', $topic_ids)
            ->where([
            ['parent_id', '=', '0'],
            ['count', '>', '0'],
            ])
            ->orderBy('name', 'asc')
            ->get();

          foreach ($rootTopics as $rootTopic) {
            $rootTopic['children'] = Topic::getTopicsChildren($rootTopic['id']);
            $results[]=$rootTopic;
          }

      } else {
          $topic = Topic::select('id')->where('name','=',$expr)->firstOrFail();
          $results = EntryTopic::select('entry_id')->where('topic_id','=',$topic->id)->get();

      }

      return  $this->prepareResult(true,$results, [],"Results created");
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


    public function sources(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }

      $sources = Entry::select(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source'))) as source, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->where($where_q)
      ->groupBy(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->orderBy(DB::raw("(JSON_UNQUOTE(JSON_EXTRACT(element, '$.source')))"))
      ->get();

      return $this->prepareResult(true, $sources, [], "All user entries");
    }

    public function authors(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $creators = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator')) as creator, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->get();

      return $this->prepareResult(true, $creators, [], "All user entries");
    }

    public function recipients(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $recipients = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient')) as recipient, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->get();

      return $this->prepareResult(true, $recipients, [], "All user entries");
    }

    public function people(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $creators = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator')) as person"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator'))"))
      ->get();

      $recipients = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient')) as person"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.recipient'))"))
      ->get();

      $people = [];
      foreach ($creators as $creator) {
        $people[] = $creator['person'];
      }
      foreach($recipients as $recipient) {
        $new_person = $recipient['person'];
        if (!in_array($new_person, $people)) {
          $people[] = $new_person;
        }
      }
      sort($people);

      return $this->prepareResult(true, $people, [], "All user entries");
    }

    public function genders(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender')) as gender, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.creator_gender'))"))
      ->get();

      return $this->prepareResult(true, $genders, [], "All user entries");
    }

    public function languages(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language')) as language, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->where($where_q)
      ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->orderBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.language'))"))
      ->get();

      return $this->prepareResult(true, $genders, [], "All user entries");
    }

    public function date_created(Request $request) {
      $status = null;
      $transcription_status = null;
      if ($request->input('status')!=="") {
        $status = $request->input('status');
      }
      if ($request->input('transcription_status')!=="") {
        $transcription_status = $request->input('transcription_status');
      }
      $where_q = [
        ['entry.current_version','=',1]
      ];
      if ($status!==null) {
        $where_q[] = ['entry.status','=', $status];
      }
      if ($transcription_status!==null) {
        $where_q[] = ['entry.transcription_status','=', $transcription_status];
      }
      $genders = Entry::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created')) as date_created, COUNT(*) AS count"))
      ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(element, '$.date_created'))"))
      ->where($where_q)
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

      $status = 1;
      $transcription_status = 2;

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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
      $status = 0;

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
            ['transcription_status','>', -1],
            ['current_version','=',1]
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
            ['transcription_status','>', -1],
            ['current_version','=',1]
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
            ['transcription_status','>', -1],
            ['current_version','=',1]
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
            ['transcription_status','>', -1],
            ['current_version','=',1]
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
            ['transcription_status','>', -1],
            ['current_version','=',1]
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
              ['status','=', $status],
              ['transcription_status','>', -1],
              ['current_version','=',1]
              ])
            ->orderBy('completed',$sort)
            ->paginate($paginate);



      } else {
        if (Entry::first() != null) {
          $items = Entry::where([
            ['status','=', $status],
            ['transcription_status','>', -1],
            ['current_version','=',1]
            ])
          ->orderBy('completed',$sort)
          ->paginate($paginate);

        } else {
            $coll = "empty bottle";
        };
      };

      return $this->prepareResult(true, $items, [], "All user entries");
    }

    public function transcriptionDeskFilteredFilters(Request $request)
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
              ['transcription_status','>', -1],
              ])
            ->orderBy('created_at', $sort)
            ->get();
      } else {
        if (Entry::first() != null) {
          $coll = Entry::where([
            ['current_version','=', 1],
            ['status','=', $status],
            ['transcription_status','>', -1],
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
            ['current_version','=',1]
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
