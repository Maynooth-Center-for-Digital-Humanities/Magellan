<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Uploadedfile;
use App\Entry;
use App\EntryTopic;
use App\Pages as Pages;
use App\EntryFormats as EntryFormats;
use DB;
use App\Helpers\PrepareOutputTrait;
use App\Http\Controllers\FileEntryController;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class AdminController extends Controller
{
  use PrepareOutputTrait;

  /**
   * Display the list of all letters set to be transcribed.
   *
   * @param  \Illuminate\Http\Request $request
   * @return Entries
   */
  public function listTranscriptions(Request $request) {
    $sort_col = null;
    $sort_dir = null;
    $transcription_status = null;
    $paginate = 10;
    $page = 1;
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
    if ($sort_col!==null && $sort_dir!==null) {
      $tsEquals = "<=";
      $tsValue = 0;
      if ($transcription_status!==null) {
        $tsEquals = "=";
        $tsValue = $transcription_status;
      }
      $transcriptions_data = Entry::where([
        ['status','=', 0],
        ['transcription_status',$tsEquals, $tsValue],
        ['current_version','=','1'],
        ])
        ->orderBy($sort_col, $sort_dir)
        ->paginate($paginate);
    }
    else {
      $transcriptions_data = Entry::where([
        ['status','=', 0],
        ['transcription_status','<=', 0],
        ['current_version','=','1'],
        ])
        ->paginate($paginate);
    }
    return $this->prepareResult(true, $transcriptions_data,$request->input(), "All users transcriptions");
  }

  public function Unauthorized(Request $request) {
    return $this->prepareResult(true, [], [], "Unauthorized access! You do not have the user rights necessary to view this page!");
  }

  public function updateTranscriptionStatus(Request $request, $id) {
    $newStatus = $request->input("status");

    $error = array();
    $entry = Entry::where('id', $id)->first();
    // active
    if ($newStatus!=="inactive") {
      $element = json_decode($entry->element, true);
      $pages = $element['pages'];
      $pagesOpen = 0;
      $pagesCompleted = 0;
      $pagesApproved = 0;
      foreach($pages as $page) {
        if (intval($page['transcription_status'])===-1 || intval($page['transcription_status'])===0) {
          $pagesOpen++;
        }
        if (intval($page['transcription_status'])===1) {
          $pagesCompleted++;
        }
        if (intval($page['transcription_status'])===2) {
          $pagesApproved++;
        }
      }
      $totalPages = count($pages);
      $error = array("open"=>$pagesOpen, "completed"=> $pagesCompleted, "approved"=>$pagesApproved, "total"=>$totalPages);
      $updateQuery = ['element'=>json_encode($element),'status'=>0, 'transcription_status'=>0];
      if ($pagesCompleted === $totalPages) {
        $updateQuery = ['transcription_status'=>1];
      }
      else if ($pagesApproved === $totalPages) {
        $updateQuery = ['status'=>1, 'transcription_status'=>2];
      }
      else if ($pagesCompleted!==$totalPages && $pagesApproved!==$totalPages && $pagesOpen===0) {
        $updateQuery = ['transcription_status'=>1];
      }
      else {
        $updateQuery = ['transcription_status'=>0];
      }

    }
    // inactive
    else {
      $updateQuery = ['transcription_status'=>-1];
    }
    Entry::whereId($id)->update($updateQuery);
    return $this->prepareResult(true, [], $error, "Letter transcription status updated successfully");
  }

  public function updateTranscriptionPage(Request $request, $id){
    $error = "";
    $entry = Entry::where('id', $id)->first();
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
    // handle entry lock
    if (!$entry->handleEntryLock()) {
      $status = false;
      return $this->prepareResult($status, $newPages, [], "This entry is in use by another user!", "Request complete");
    }

    $element['pages']=$newPages;
    Entry::whereId($id)->update(['element'=>json_encode($element)]);
    return $this->prepareResult(true, $newPages, $error, "Page transcription updated successfully");
  }

  public function updateTranscriptionPageStatus(Request $request, $id) {
    $error = array();
    $entry = Entry::where('id', $id)->first();
    $entryTranscriptionStatus = $entry->transcription_status;
    $archive_filename = $request->input('archive_filename');
    $transcription_status = $request->input('transcription_status');
    $element = json_decode($entry->element, true);
    $pages = $element['pages'];
    $newPages = array();
    $pagesOpen = 0;
    $pagesCompleted = 0;
    $pagesApproved = 0;
    foreach($pages as $page) {
      if ($page['archive_filename']!==$archive_filename) {
        if (intval($page['transcription_status'])===0) {
          $pagesOpen++;
        }
        if (intval($page['transcription_status'])===1) {
          $pagesCompleted++;
        }
        if (intval($page['transcription_status'])===2) {
          $pagesApproved++;
        }
      }
      else if ($page['archive_filename']===$archive_filename) {
        $page['transcription_status'] = intval($transcription_status);
        if (intval($page['transcription_status'])===0) {
          $pagesOpen++;
        }
        if (intval($transcription_status)===1) {
          $pagesCompleted++;
        }
        if (intval($transcription_status)===2) {
          $pagesApproved++;
        }
      }
      $newPages[]=$page;
    }
    $element['pages']=$newPages;
    $totalPages = count($pages);
    $error = array("completed"=> $pagesCompleted, "approved"=>$pagesApproved);
    $newTranscriptionStatus = 0;
    if ($entryTranscriptionStatus===-1) {
      $newTranscriptionStatus = -1;
    }

    $entry = Entry::find($id);

    if ($pagesCompleted === $totalPages) {
      if ($entryTranscriptionStatus>-1) {
        $newTranscriptionStatus = 1;
      }
      $entry->element = json_encode($element);
      $entry->transcription_status = $newTranscriptionStatus;
      $entry->save();
    }
    else if ($pagesApproved === $totalPages) {
      if ($entryTranscriptionStatus>-1) {
        $newTranscriptionStatus = 2;
      }
      $entry->element = json_encode($element);
      $entry->status = 1;
      $entry->transcription_status = $newTranscriptionStatus;
      $entry->save();
    }
    else if ($pagesCompleted!==$totalPages && $pagesApproved!==$totalPages && $pagesOpen===0) {
      if ($entryTranscriptionStatus>-1) {
        $newTranscriptionStatus = 1;
      }

      $entry->element = json_encode($element);
      $entry->transcription_status = $newTranscriptionStatus;
      $entry->save();
    }
    else {
      $entry->element = json_encode($element);
      $entry->status = 0;
      $entry->transcription_status = $newTranscriptionStatus;
      $entry->save();
    }

    return $this->prepareResult(true, $newPages, Entry::find($id), "Page transcription status updated successfully");
  }

  public function adminsearch(Request $request, $sentence) {
      $sort_col = "element->title";
      $sort_dir = "desc";
      $status = null;
      $transcription_status = null;
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

      $sanitize_sentence = (filter_var(strtolower($sentence), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH));

      $where_q = [];
      $where_q[] = ['entry.current_version','=','1'];
      if ($status!==null) {
        $status_q = ['entry.status','=',$status];
        $where_q[] = $status_q;
      }
      $tsEquals = "<=";
      $tsValue = 0;
      if ($transcription_status!==null) {
        $tsEquals = "=";
        $tsValue = $transcription_status;
        $transcription_status_q = ['entry.transcription_status',$tsEquals, $tsValue];
        $where_q[] = $transcription_status_q;
      }

      $match_sql = "( LOWER(`element`->>'$.title') like '%".$sanitize_sentence."%' or LOWER(`element`->>'$.description') like '%".$sanitize_sentence."%' or LOWER(`fulltext`) like '%".$sanitize_sentence."%' )";
      DB::enableQueryLog();
      $entries = Entry::select('entry.*')
        ->where($where_q)
        ->whereRaw(DB::Raw($match_sql))
        ->groupBy('id')
        ->orderBy($sort_col, $sort_dir)
        ->paginate($paginate);

      return $this->prepareResult(true, $entries, DB::getQueryLog(), "Results created");

  }

  public function adminAdvancedSearch (Request $request) {
      $sort_col = "element->title";
      $sort_dir = "desc";
      $page = 1;
      $paginate = 10;
      $status = null;
      $transcription_status = null;
      $query = null;
      if ($request->input('sort_col') !== null && $request->input('sort_col') !== "") {
          $sort_col = $request->input('sort_col');
      }
      if ($request->input('sort_dir') !== null && $request->input('sort_dir') !== "") {
          $sort_dir = $request->input('sort_dir');
      }
      if ($request->input('page') !== null && $request->input('page') !== "") {
          $page = $request->input('page');
      }
      if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
          $paginate = $request->input('paginate');
      }
      if ($request->input('status') !== null && $request->input('status') !== "") {
          $status = $request->input('status');
      }
      if ($request->input('transcription_status') !== null && $request->input('transcription_status') !== "") {
          $transcription_status = $request->input('transcription_status');
      }
      if ($request->input('query') !== null && $request->input('query') !== "") {
          $query = $request->input('query');
      }

      // topics

      if ($query===null) {
        return $this->prepareResult(true, [], [], "You must set a query to search");
      }

      $newQuery = "";
      $error = "";
      $i=0;
      $where_q = [];
      $where_q[] = ['entry.current_version','=','1'];
      $queryLength = count($query);

      foreach($query as $queryRow1) {
        $queryDecode1 = json_decode($queryRow1, true);
        $type1 = $queryDecode1['type'];
        if ($type1==="topics") {
          $queryLength = $queryLength-1;
        }
      }
      $entry_ids = [];
      foreach($query as $queryRow) {
        $queryDecode = json_decode($queryRow, true);
        $type = $queryDecode['type'];
        $operator = $queryDecode['operator'];
        $value = $queryDecode['value'];
        $boolean_operator = $queryDecode['boolean_operator'];

        if ($type!=="topics") {
          $sanitize_value = filter_var(strtolower($value), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
          $sanitize_query_value = $sanitize_value;
          if ($sanitize_value!=="") {
            $sanitize_query_value = "'".$sanitize_value."'";
          }
          $queryOperator = "";
          if ($operator==="equals") {
            $queryOperator = "=";
          }
          else if ($operator==="contains") {
            $queryOperator = "like";
            $sanitize_query_value = "'%".$sanitize_value."%'";
          }
          else if ($operator==="not_contains") {
            $queryOperator = "not like";
          }
          else if ($operator==="empty") {
            $queryOperator = "=''";
          }
          else if ($operator==="not_empty") {
            $queryOperator = "!=''";
          }
          if (!(($operator==="equals" || $operator==="contains" || $operator==="not_contains") && $sanitize_query_value==="")) {
            $i++;
          }
          if ($i===$queryLength) {
            $boolean_operator = "";
          }
          if (!(($operator==="equals" || $operator==="contains" || $operator==="not_contains") && $sanitize_query_value==="")) {
            $newQuery.= " LOWER(".$type.") ".$queryOperator." ".$sanitize_query_value." ".$boolean_operator;
          }
        }

        if ($type==="topics") {
          $topic_ids = [];
          foreach($value as $topic) {
            $topic_id = $topic['value'];
            $topic_ids[]=$topic_id;
          }

          $keywords_entry_ids = EntryTopic::select('entry_id')
          ->whereIn('topic_id',$topic_ids)
          ->groupBy('entry_id')
          ->get();
          foreach($keywords_entry_ids as $entry_id) {
            $entry_ids[]=$entry_id['entry_id'];
          }
        }


      }

      $where_q = [];
      if ($status!==null) {
        $status_q = ['entry.status','=',$status];
        $where_q[] = $status_q;
      }
      $tsEquals = "<=";
      $tsValue = 0;
      if ($transcription_status!==null) {
        $tsEquals = "=";
        $tsValue = $transcription_status;
        $transcription_status_q = ['entry.transcription_status',$tsEquals, $tsValue];
        $where_q[] = $transcription_status_q;
      }

      $case=0;
      if ($newQuery!=="" && $entry_ids===[]) {
        $entries = Entry::select('entry.*')
          ->where($where_q)
          ->whereRaw(DB::Raw($newQuery))
          ->groupBy('id')
          ->orderBy($sort_col, $sort_dir)
          ->paginate($paginate);
      }
      else if ($newQuery!=="" && $entry_ids!==[]) {
        $case=2;
        $entries = Entry::select('entry.*')
          ->where($where_q)
          ->whereIn('id',$entry_ids)
          ->whereRaw(DB::Raw($newQuery))
          ->groupBy('id')
          ->orderBy($sort_col, $sort_dir)
          ->paginate($paginate);
      }
      else if ($newQuery==="" && $entry_ids!==[]) {
        $case=3;
        $entries = Entry::select('entry.*')
          ->where($where_q)
          ->whereIn('id',$entry_ids)
          ->groupBy('id')
          ->orderBy($sort_col, $sort_dir)
          ->paginate($paginate);
      }
      else {
        $entries = [];
        $error = "Please provide a valid search query";
      }

      return $this->prepareResult(true, $entries, $case, $entry_ids);

  }


  public function list(Request $request) {
      $sort_col = "";
      $sort_dir = "desc";
      $status = null;
      $transcription_status = null;
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
      if ($sort_col==="score") {
        $sort_col = "element->title";
      }

      $where_q = [];
      $where_q[] = ['entry.current_version','=','1'];
      if ($status!==null) {
        $status_q = ['entry.status','=',$status];
        $where_q[] = $status_q;
      }
      $tsEquals = "<=";
      $tsValue = 0;
      if ($transcription_status!==null) {
        $tsEquals = "=";
        $tsValue = $transcription_status;
        $transcription_status_q = ['entry.transcription_status',$tsEquals, $tsValue];
        $where_q[] = $transcription_status_q;
      }
      $items = Entry::select()
        ->where($where_q)
        ->orderBy($sort_col, $sort_dir)
        ->paginate($paginate);

      return $this->prepareResult(true, $items, [], "Results created");

  }

  public function updateEntry(Request $request, $id) {
      $formData = $request->all();

      $error = [];
      $responseMsg = "";

      $now = date("Y-m-d\TH:i:sP");

      // topics
      $topics = array();
      $keywords = $formData['keywords'];
      foreach ($keywords as $keyword) {
        $topic = array(
          "topic_id"=>$keyword['topic_id'],
          "topic_name"=>$keyword['label']
        );
        $topics[] = $topic;
      }

      // date
      $date_created = (string)$formData['year'];
      if (isset($formData['month']) && $formData['month']!=="") {
        $date_created .= "-".$formData['month'];
      }
      if (isset($formData['day']) && $formData['day']!=="") {
        $date_created .= "-".$formData['day'];
      }

      $notes = "";
      if ($request->exists['notes']) {
        $notes = $formData['notes'];
      }
      $title = "";
      if ($formData['title']!==null) {
        $title = $formData['title'];
      }
      $source = "";
      if ($formData['source']!==null) {
        $source = $formData['source'];
      }
      $creator = "";
      if ($formData['creator']!==null) {
        $creator = $formData['creator'];
      }
      $creator_gender = "";
      if ($formData['creator_gender']!==null) {
        $creator_gender = $formData['creator_gender'];
      }
      $creator_location = "";
      if ($formData['creator_location']!==null) {
        $creator_location = $formData['creator_location'];
      }
      $language = "";
      if ($formData['language']!==null) {
        $language = $formData['language'];
      }
      $recipient = "";
      if ($formData['recipient']!==null) {
        $recipient = $formData['recipient'];
      }
      $additional_information = "";
      if ($formData['additional_information']!==null) {
        $additional_information = $formData['additional_information'];
      }
      $document_id = "";
      if ($formData['document_id']!==null) {
        $document_id = $formData['document_id'];
      }
      $doc_collection = "";
      if ($formData['doc_collection']!==null) {
        $doc_collection = $formData['doc_collection'];
      }
      $recipient_location = "";
      if ($formData['recipient_location']!==null) {
        $recipient_location = $formData['recipient_location'];
      }
      $year_of_death_of_author = "";
      if ($formData['year_of_death_of_author']!==null) {
        $year_of_death_of_author = $formData['year_of_death_of_author'];
      }

      if (intval($id)===0) {
        // pages
        $pages = [];

        $json_element = array(
          "type"=>"uploader",
  	      "debug"=>"",
  	      "pages"=> $pages,
          "title"=> $title,
        	"source"=> $source,
          "topics"=> $topics,
          "creator"=> $creator,
        	"creator_gender"=> $creator_gender,
          "creator_location"=>$creator_location,
          "user_id"=> Auth::user()->id,
          "language" => $language,
          "recipient" => $recipient,
          "time_zone"=> "Europe/Dublin",
          "collection"=> "",
          "api_version"=> "1.0",
          "description"=> $additional_information,
          "document_id"=> $document_id,
          "date_created"=>$date_created,
          "number_pages"=>0,
          "request_time"=>$now,
          "terms_of_use"=>'',
          "collection_id"=>"",
          "doc_collection"=>$doc_collection,
          "modified_timestamp"=>$now,
          "recipient_location"=>$recipient_location,
          "copyright_statement"=>'',
          "year_of_death_of_author"=>$year_of_death_of_author,
        );
        $format = "admin_metadata";
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
            $entry->notes = $notes;
            $entry->save();
            $error['files']=$imgs_errors;

            return $this->prepareResult(true, $entry, $error, "Entry created");
        }

      }
      else if (intval($id)>0) {

        $entry = Entry::where('id',$id)->first();
        $entryElement = json_decode($entry->element, true);
        // element

        $json_element = array(
          "type"=>$entryElement['type'],
  	      "debug"=>"",
  	      "pages"=> $entryElement['pages'],
          "title"=> $title,
        	"source"=> $source,
          "topics"=> $topics,
          "creator"=> $creator,
        	"creator_gender"=> $creator_gender,
          "creator_location"=>$creator_location,
          "user_id"=> Auth::user()->id,
          "language" => $language,
          "recipient" => $recipient,
          "time_zone"=> "Europe/Dublin",
          "collection"=> $entryElement['collection'],
          "api_version"=> "1.0",
          "description"=> $additional_information,
          "document_id"=> $entryElement['document_id'],
          "date_created"=>$date_created,
          "number_pages"=>$entryElement['number_pages'],
          "request_time"=>$now,
          "terms_of_use"=>$entryElement['terms_of_use'],
          "collection_id"=>$entryElement['collection_id'],
          "doc_collection"=>$doc_collection,
          "modified_timestamp"=>$now,
          "recipient_location"=>$recipient_location,
          "copyright_statement"=>$entryElement['copyright_statement'],
          "year_of_death_of_author"=>$formData['year_of_death_of_author'],
        );

        $error = false;
        $format = "admin_metadata";
        $entry_format = EntryFormats\Factory::create($format);
        $validator = $entry_format->valid($json_element);
        if ($validator->fails()) {
            $error = true;
            $errors = $validator->errors();

            return $this->prepareResult(false, [], $errors, "Error in updating entry");
        }
        else {
          $entry->element = json_encode($json_element);
          $entry->notes = $notes;
          $entry->save();

          $json_element['notes'] = $formData['notes'];
          return $this->prepareResult(true, $json_element, $error, "Entry updated successfully");
        }
      }

    }

  public function uploadEntryPage(Request $request, $id) {
    $postData = $request->all();
    $now = date("Y-m-d\TH:i:sP");
    $image = $request->file('image');
    $uploaded_image_type = $postData['additional_img_info'];
    $imgs_errors = array();
    if (intval($id)>0) {
      // upload image and store
      $newPage = [];
      $fileEntryController = new FileEntryController();
      if ($fileEntryController->isImage($image)) {
        $extension=$image->getClientOriginalExtension();
        $filename = $image->getFilename().'.'.strtolower($extension);

        Storage::disk('fullsize')->put($filename, File::get($image));

        $fileEntryController->makeThumbnail($filename, 200);

        $saved_file = $fileEntryController->store($image, "uploader page", Auth::user()->id);


        $image_type = "Letter";
        if ($uploaded_image_type!=="") {
          $image_type = $uploaded_image_type;
        }
        $newPage = array(
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
      }
      else {
        $imgs_errors[] = array("filename"=>$image->getClientOriginalName());
      }

      if (count($newPage)>0) {
        $entry = Entry::where('id',$id)->first();
        $element = json_decode($entry->element, true);
        $pages = $element['pages'];
        $pages[] = $newPage;
        $element['pages'] = $pages;
        $entry->element = json_encode($element);
        $newTranscriptionStatus = $entry->transcription_status;
        if ($newTranscriptionStatus!==-1) {
          $newTranscriptionStatus = 0;
        }
        $entry->transcription_status = $newTranscriptionStatus;
        $entry->save();
        return $this->prepareResult(true, $entry->element, [], "Entry updated successfully");
      }
      else {
        return $this->prepareResult(true, [], $imgs_errors, "Entry update error");
      }

    }
  }

  public function adminUserLetter(Request $request, $id) {
    $user = Auth::user();
    $entry = Entry::where('id',$id)->first();
    $msg = "Entry found";
    $status = true;

    if ($entry != null) {
      $coll = json_decode($entry->element, true);
      $copyright_statement = strip_tags($coll['copyright_statement']);
      $coll['copyright_statement'] = $copyright_statement;
      $coll['notes'] = $entry->notes;
      $coll['entry'] = $entry;
      $file_id = $entry->uploadedfile_id;
      if ($file_id!==null) {
        $file = Uploadedfile::select('id', 'filename', 'original_filename')
            ->where('id',$file_id)->get();
        $link = asset('/').'download-xml/'.$file_id;
        $newFile = $file[0];
        $newFile['link'] = $link;
        $coll["file"] = $newFile;
      }

      // siblings
      $siblings = Entry::where([
        ['element->document_id','=',$coll['document_id']],
        ['id','!=',$id]
        ])->get();
      $coll['siblings']=$siblings;

    } else {
      $coll = "";
      $msg = "Entry not found";
      $status = false;

      abort(404, json_encode($this->prepareResult($status, $coll, [], $msg, "Request failed")));
    };

    return $this->prepareResult($status, $coll, [], $msg, "Request complete");
  }

  public function updateEntryStatus(Request $request, $id) {
    $entry = Entry::where('id',$id)->first();
    $status = $request->input("status");
    $transcription_status = $request->input("transcription_status");
    $coll = array($status ,$transcription_status);
    $msg = [];


    return $this->prepareResult(true, $coll, [], $msg, "Request complete");
  }

  public function writeXML(Request $request, $id) {
    $entry = Entry::where('id',$id)->first();
    $element = json_decode($entry->element, true);

    // revisors & contributors, facsimile
    $revisors = array();
    $contributors = array();
    $revisorsXML = "";
    $contributorsXML = "";
    $facsimile = "";
    $facsimileXML = "";
    $transcriptionXML = "";
    $transcription = "";
    foreach ($element['pages'] as $page) {
      $revName = $page['rev_name'];
      $contributor = $page['contributor'];
      if ($revName!=="" && !in_array($revName, $revisors)) {
        $revisors[] = $revName;
        $revisorsXML .= '<respStmt>
          <resp>Editor</resp>
          <name>'.$revName.'</name>
        </respStmt>';
      }
      if ($contributor!=="" && !in_array($contributor, $contributors)) {
        $contributors[] = $contributor;
        $contributorsXML .= '<respStmt>
          <resp>Contributor</resp>
          <name>'.$contributor.'</name>
        </respStmt>';
      }
      if ($page['archive_filename']!=="") {
        $facsimile .= '<graphic xml:id="L1916_960_img_'.$page['page_id'].'" url="'.$page['archive_filename'].'"/>';
      }
      if ($page['transcription']!=="") {
        $pb = '<pb n="'.$page['page_id'].'" facs="#L1916_960_img_'.$page['page_id'].'"/>';
        $typeAttr = "";
        if ($page['page_type']!=="") {
          $typeAttr = ' type="'.$page['page_type'].'" ';
        }
        $transcription .= $pb.'
        <text '.$typeAttr.'>
          <body>'
          .$page['transcription']
        .'  </body>
        </text>';
      }
    }
    if ($facsimile!=="") {
      $facsimileXML = '<facsimile>
        '.$facsimile.'
      </facsimile>';
    }
    if ($transcription!=="") {
      $transcriptionXML = '<text>
        <group>
          '.$transcription.'
        </group>
      </text>';
    }
    // notes
    $notesStmt = "";
    if ($element['description']!=="") {
      $notesStmt = '<notesStmt>
        <note type="summary">
          <p>'.$element['description'].'</p>
        </note>
      </notesStmt>';
    }
    // source
    $sourceDesc = "";
    if ($element['source']!=="") {
      $idno = "";
      if ($element['doc_collection']!=="") {
        $idno = $element['doc_collection'];
      }
      $sourceDesc = '<sourceDesc>
        <msDesc>
          <msIdentifier>
            <repository>'.$element['source'].'</repository>
            <idno>'.$idno.'</idno>
          </msIdentifier>
        </msDesc>
      </sourceDesc>';
    }
    // correspondents
    // creator
    $creator = "";
    $creator_location = "";
    $date_created = "";
    if ($element['creator']!=="") {
      $creator = '<persName>'.$element['creator'].'</persName>';
    }
    if ($element['creator_location']!=="") {
      $creator_location = '<placeName>'.$element['creator_location'].'</placeName>';
    }
    if ($element['date_created']!=="") {
      $date_hr = "";
      $dateArr = explode("-", $element['date_created']);
      $year = $dateArr[0];
      $month = null;
      $day = null;
      $months = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
      $newMonth = "";
      if (isset($dateArr[1])) {
        $month = intval($dateArr[1])-1;
        $newMonth = $months[$month];
      }
      if (isset($dateArr[2])) {
        $day = $dateArr[2];
      }
      $date_created = '<date when="'.$element['date_created'].'">'.$day.' '.$newMonth.' '.$year.'</date>';
    }
    $senderXML = "";
    if ($element['creator']!=="" || $element['creator_location']!=="" || $element['date_created']!=="") {
      $senderXML = '<correspAction type="sent">
        '.$creator.'
        '.$creator_location.'
        '.$date_created.'
      </correspAction>';
    }
    // recipient
    $recipient = "";
    $recipient_location = "";
    if ($element['recipient']!=="") {
      $recipient = '<persName>'.$element['recipient'].'</persName>';
    }
    if ($element['recipient_location']!=="") {
      $recipient_location = '<placeName>'.$element['recipient_location'].'</placeName>';
    }
    $recipientXML = "";
    if ($element['recipient']!=="" || $element['recipient_location']!=="") {
      $recipientXML = '<correspAction type="received">
        '.$recipient.'
        '.$recipient_location.'
      </correspAction>';
    }
    $correspDesc = "";
    if ($recipientXML!=="" || $senderXML!=="") {
      $correspDesc = '<correspDesc>
        '.$recipientXML.'
        '.$senderXML.'
      </correspDesc>';
    }

    // topics
    $topicsXML = "";
    if (count($element['topics'])>0 || $element['creator_gender']!=="") {
      $creator_gender = "";
      if ($element['creator_gender']!=="") {
        $creator_gender = '<item n="gender">'.$element['creator_gender'].'</item>';
      }
      $error_topics = $element['topics'];
      $topicsItems = "";
      foreach ($element['topics'] as $topic) {
        $topicsItems .= '<item n="tag">'.$topic['topic_name'].'</item>';
      }
      if ($creator_gender!=="" || $topicsItems!=="") {
        $topicsXML = '<textClass>
        <keywords>
          <list>
            '.$creator_gender.'
            '.$topicsItems.'
          </list>
        </keywords>
      </textClass>';
      }
    }

    // language
    $langUsage = "";
    if ($element['language']!=="") {
      $langUsage = '<langUsage>
        <language>'.$element['language'].'</language>
      </langUsage>';
    }

    $newXML = '<?oxygen RNGSchema="https://raw.githubusercontent.com/bleierr/Letters-1916-sample-files/master/plain%20corresp%20templates/template.rng" type="xml"?>
    <TEI xmlns="http://www.tei-c.org/ns/1.0">
      <teiHeader xml:id="L1916_'.$element['document_id'].'"">
        <fileDesc>
          <titleStmt>
            <title>'.$element['title'].'</title>
            <author>'.$element['creator'].'</author>
          </titleStmt>
          <editionStmt>
            <edition>The Letters of 1916</edition>
            <respStmt>
              <resp>General Editor</resp>
              <name xml:id="SS">Susan Schreibman</name>
            </respStmt>
            '.$revisorsXML.'
            '.$contributorsXML.'
          </editionStmt>
          <publicationStmt>
            <publisher>
              <address>
                <name>An Foras Feasa</name>
                <orgName>Maynooth University</orgName>
                <placeName>
                  <settlement>Maynooth</settlement>
                  <region>Co. Kildare</region>
                  <country>IRE</country>
                </placeName>
              </address>
            </publisher>
            <idno>L1916_'.$element['document_id'].'</idno>
            <availability status="restricted">
              <p>All rights reserved. No part of this image may be reproduced, distributed, or
    transmitted in any form or by any means, including photocopying, recording, or other
    electronic or mechanical methods, without the prior written permission of the institutional
    or private owner of the image and the Letters of 1916 project. For permission requests,
    write to the Letters of 1916 at letters1916@gmail.com. </p>
            </availability>
            <date when="'.$entry->created_at.'">'.$entry->created_at.'</date>
          </publicationStmt>
          '.$notesStmt.'
          '.$sourceDesc.'
        </fileDesc>
        <profileDesc>
          '.$correspDesc.'
          '.$topicsXML.'
          '.$langUsage.'
        </profileDesc>
      </teiHeader>
      '.$facsimileXML.'
      '.$transcriptionXML.'
    </TEI>';

    return $this->prepareResult(true, $entry, $newXML, $element, "Request complete");
  }
}
