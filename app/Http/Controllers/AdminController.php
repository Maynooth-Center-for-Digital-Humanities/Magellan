<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Uploadedfile;
use App\Entry;
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
      $pagesCompleted = 0;
      $pagesApproved = 0;
      foreach($pages as $page) {
        if (intval($page['transcription_status'])===1) {
          $pagesCompleted++;
        }
        if (intval($page['transcription_status'])===2) {
          $pagesApproved++;
        }
      }
      $totalPages = count($pages);
      $error = array("completed"=> $pagesCompleted, "approved"=>$pagesApproved);
      $updateQuery = ['element'=>json_encode($element),'status'=>0, 'transcription_status'=>0];
      if ($pagesCompleted === $totalPages) {
        $updateQuery = ['transcription_status'=>1];
      }
      else if ($pagesApproved === $totalPages) {
        $updateQuery = ['status'=>1, 'transcription_status'=>2];
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
    $archive_filename = $request->input('archive_filename');
    $transcription_status = $request->input('transcription_status');
    $element = json_decode($entry->element, true);
    $pages = $element['pages'];
    $newPages = array();
    $pagesCompleted = 0;
    $pagesApproved = 0;
    foreach($pages as $page) {
      if ($page['archive_filename']!==$archive_filename) {
        if (intval($page['transcription_status'])===1) {
          $pagesCompleted++;
        }
        if (intval($page['transcription_status'])===2) {
          $pagesApproved++;
        }
      }
      else if ($page['archive_filename']===$archive_filename) {
        $page['transcription_status'] = intval($transcription_status);
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
    $updateQuery = ['element'=>json_encode($element),'status'=>0, 'transcription_status'=>0];
    if ($pagesCompleted === $totalPages) {
        $updateQuery = ['element'=>json_encode($element), 'transcription_status'=>1];
    }
    else if ($pagesApproved === $totalPages) {
      $updateQuery = ['element'=>json_encode($element),'status'=>1, 'transcription_status'=>2];
    }

    Entry::whereId($id)->update($updateQuery);


    return $this->prepareResult(true, $newPages, $error, "Page transcription status updated successfully");
  }

  public function adminsearch(Request $request, $sentence) {
      $sort_col = "score";
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

      $sanitize_sentence = filter_var($sentence, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

      $match_sql = "match(`pages`.`title`,`pages`.`description`,`pages`.`text_body`) against ('$sanitize_sentence' in boolean mode)";

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

      $pages = Pages::select('entry_id', 'entry.id as id', 'title', 'description', DB::raw(($match_sql) . "as score"), 'page_number', 'pages.transcription_status as page_transcription_status')
        ->join('entry', 'entry.id','=','pages.entry_id')
        ->with('entry')
        ->where($where_q)
        ->whereRaw($match_sql)
        ->orderBy($sort_col, $sort_dir)
        ->paginate($paginate);


      return $this->prepareResult(true, $pages, $where_q, "Results created");

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

      $entries = Entry::select()
        ->where($where_q)
        ->orderBy($sort_col, $sort_dir)
        ->paginate($paginate);

      return $this->prepareResult(true, $entries, [], "Results created");

  }

  public function updateEntry(Request $request, $id) {
      $formData = $request->all();

      $error = [];
      $responseMsg = "";
      //return $this->prepareResult(true, $formData['notes'], $error, $responseMsg);

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

      if (intval($id)===0) {
        // pages
        $pages = [];

        $json_element = array(
          "type"=>"uploader",
  	      "debug"=>"",
  	      "pages"=> $pages,
          "title"=> $formData['title'],
        	"source"=> $formData['source'],
          "topics"=> $topics,
          "creator"=> $formData['creator'],
        	"creator_gender"=> $formData['creator_gender'],
          "creator_location"=>$formData['creator_location'],
          "user_id"=> Auth::user()->id,
          "language" => $formData['language'],
          "recipient" => $formData['recipient'],
          "time_zone"=> "Europe/Dublin",
          "collection"=> "",
          "api_version"=> "1.0",
          "description"=> $formData['additional_information'],
          "document_id"=> $formData['document_id'],
          "date_created"=>$date_created,
          "number_pages"=>0,
          "request_time"=>$now,
          "terms_of_use"=>'',
          "collection_id"=>"",
          "doc_collection"=>$formData['doc_collection'],
          "modified_timestamp"=>$now,
          "recipient_location"=>$formData['recipient_location'],
          "copyright_statement"=>'',
          "year_of_death_of_author"=>$formData['year_of_death_of_author'],
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
          "title"=> $formData['title'],
        	"source"=> $formData['source'],
          "topics"=> $topics,
          "creator"=> $formData['creator'],
        	"creator_gender"=> $formData['creator_gender'],
          "creator_location"=>$formData['creator_location'],
          "user_id"=> Auth::user()->id,
          "language" => $formData['language'],
          "recipient" => $formData['recipient'],
          "time_zone"=> "Europe/Dublin",
          "collection"=> $entryElement['collection'],
          "api_version"=> "1.0",
          "description"=> $formData['additional_information'],
          "document_id"=> $entryElement['document_id'],
          "date_created"=>$date_created,
          "number_pages"=>$entryElement['number_pages'],
          "request_time"=>$now,
          "terms_of_use"=>$entryElement['terms_of_use'],
          "collection_id"=>$entryElement['collection_id'],
          "doc_collection"=>$formData['doc_collection'],
          "modified_timestamp"=>$now,
          "recipient_location"=>$formData['recipient_location'],
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

            return $this->prepareResult(false, $errors, $errors, "Error in updating entry");
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
    $image = $postData['image'];
    $uploaded_image_type = $postData['additional_img_info'];

    $imgs_errors = array();
    if (intval($id)>0) {
      // upload image and store
      $newPage = [];
      $fileEntryController = new FileEntryController();
      if ($fileEntryController->isImage($image)) {
        $extension=$image->getClientOriginalExtension();
        $filename = $image->getFilename().'.'.$extension;
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
}
