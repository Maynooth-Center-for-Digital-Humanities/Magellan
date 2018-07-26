<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Entry;
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
    $sort = "asc";
    $paginate = 10;
    $page = 1;
    if ($request->input('sort') !== null && $request->input('sort') !== "") {
        $sort = $request->input('sort');
    }
    if ($request->input('paginate') !== null && $request->input('paginate') !== "") {
        $paginate = $request->input('paginate');
    }
    if ($request->input('page') !== null && $request->input('page') !== "") {
        $page = $request->input('page');
    }
    $transcriptions_data = Entry::where([
      ['status','=', 0],
      ['transcription_status','<=', 0],
      ])
      ->orderBy('created_at', $sort)
      ->paginate($paginate);
    $count = count($transcriptions_data);
    return $this->prepareResult(true, $transcriptions_data, [], "All users transcriptions");
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

}
