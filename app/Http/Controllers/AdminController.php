<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Entry;
use App\Pages as Pages;
use DB;
use App\Helpers\PrepareOutputTrait;
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
    $error = array();
    $entry = Entry::where('id', $id)->first();
    $transcription_status = -1;
    $current_transcription_status = $entry->transcription_status;
    if ($current_transcription_status===-1) {
      $transcription_status = 0;
    }
    Entry::whereId($id)->update(['transcription_status'=>$transcription_status]);
    return $this->prepareResult(true, $transcription_status, $error, "Letter transcription status updated successfully to ".$transcription_status);
  }

  public function updateTranscriptionPageStatus(Request $request, $id) {
    $error = array();
    $entry = Entry::where('id', $id)->first();
    $archive_filename = $request->input('archive_filename');
    $transcription_status = $request->input('transcription_status');
    $element = json_decode($entry->element, true);
    $pages = $element['pages'];
    $newPages = array();
    foreach($pages as $page) {
      if ($page['archive_filename']===$archive_filename) {
        $page['transcription_status'] = intval($transcription_status);
      }
      $newPages[]=$page;
    }
    $element['pages']=$newPages;
    Entry::whereId($id)->update(['element'=>json_encode($element)]);
    return $this->prepareResult(true, $newPages, $error, "Page transcription status updated successfully");
  }

  public function adminsearch(Request $request, $sentence) {
      $sort_col = "score";
      $sort_dir = "desc";
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

      $sanitize_sentence = filter_var($sentence, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

      $match_sql = "match(`pages`.`title`,`pages`.`description`,`pages`.`text_body`) against ('$sanitize_sentence' in boolean mode)";

      $paginate = 10;
      if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
          $paginate = $request->input('paginate');
      }

      if ($sort_col!==null && $sort_dir!==null) {
        $tsEquals = "<=";
        $tsValue = 0;
        if ($transcription_status!==null) {
          $tsEquals = "=";
          $tsValue = $transcription_status;
        }

        $pages = Pages::select('entry_id', 'title', 'description', DB::raw(($match_sql) . "as score"), 'page_number')
          ->join('entry', 'entry.id','=','pages.entry_id')
          ->with('entry')
          ->where([
            ['entry.current_version','=','1'],
            ['entry.status','=','0'],
            ['entry.transcription_status',$tsEquals, $tsValue]
          ])
          ->whereRaw($match_sql)
          ->orderBy($sort_col, $sort_dir)
          ->paginate($paginate);
        }
        else {
          $pages = Pages::select('entry_id', 'title', 'description', DB::raw(($match_sql) . "as score"), 'page_number')
            ->join('entry', 'entry.id','=','pages.entry_id')
            ->with('entry')
            ->where([
              ['entry.current_version','=','1'],
              ['entry.status','=','0'],
              ['entry.transcription_status','<=', '0']
            ])
            ->whereRaw($match_sql)
            ->orderBy($sort_col, $sort_dir)
            ->paginate($paginate);
        }
      return $this->prepareResult(true, $pages, $sanitize_sentence, "Results created");

  }

}
