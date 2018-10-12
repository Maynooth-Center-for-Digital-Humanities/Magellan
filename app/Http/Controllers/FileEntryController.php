<?php

namespace App\Http\Controllers;

use App\Uploadedfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use App\Entry;
use DB;
use App\Helpers;

use App\EntryFormats\Factory as EntryFormatFactory;

class FileEntryController extends Controller
{
  use Helpers\PrepareOutputTrait;
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {

        $entries = Uploadedfile::all();

        return $entries;
    }

    public function add(Request $request) {
      $files = $request->file('data');
      $format = $request->input('format');
      $document_id = 0;
      $entry = [];
      if ($request->hasFile('data') && $format!=="") {
        foreach ($files as $file) {
          $entry_format = EntryFormatFactory::create($format);

          $extension=$file->getClientOriginalExtension();
          $extensionLower = strtolower($extension);
          Storage::disk('local')->put($file->getFilename().'.'.$extensionLower,  File::get($file));
          if($entry_format->valid($file)){

              // Store the file
              $saved_file = $this->store($file,$format,Auth::user()->id);

              $decode = json_decode($entry_format->getJsonData(),true);
              $document_id = $decode['document_id'];
              $entryPages = $decode['pages'];

              $entry = new Entry();
              $entry->element = $entry_format->getJsonData();
              $entry->user_id = Auth::user()->id;
              $entry->current_version = TRUE;
              $entry->status = $this->getEntryStatus($entryPages);
              $entry->transcription_status = 2;
              $entry->notes = "";
              $entry->uploadedfile_id = $saved_file->id;

              // if there are other entries with the same document id set their current_version to zero
              $other_versions = DB::table('entry')
                ->join('uploadedfile', 'entry.uploadedfile_id','=','uploadedfile.id')
                ->select('entry.id')
                ->where('uploadedfile.original_filename',$document_id.".xml")
                ->where('entry.current_version','1')->get();

              foreach($other_versions as $instance) {
                DB::table('entry')
                ->where('id', $instance->id)
                ->update(['current_version' => 0]);
              }

              // save entry
              $entry->save();

          }
          else {
            return $this->prepareResult(200, $response, "File not valid", "");
          }
        }
        if (count($files)===1) {
          $response = array("document_id"=>$document_id);
          if ($document_id>0) {
            return $this->prepareResult(200, $response, [], "File Uploaded Successfully");
          }
          else {
            return $this->prepareResult(false, $response, [], "Entry was not created");
          }
        }
        else {
          return new Response("Files Uploaded Successfully", 200);
        }


      }

    }


    public function get($filename){

        $entry = Uploadedfile::where('filename', '=', $filename)->firstOrFail();
        $file = Storage::disk('local')->get($entry->filename);

        return (new Response($file, 200))
            ->header('Content-Type', $entry->mime);
    }


    public function store($file, $format, $user){

        $extensionLower = strtolower($file->getClientOriginalExtension());
        $entry = new Uploadedfile();
        $entry->mime = $file->getClientMimeType();
        $entry->original_filename = $file->getClientOriginalName();
        $entry->filename = $file->getFilename().'.'.$extensionLower;
        $entry->format = $format;
        $entry->user_id = $user;
        $entry->filesize = $file->getClientSize();
        $entry->save();

        return $entry;

    }

    public function downloadXML($fileid) {

      $fileUpload = Uploadedfile::where("id", $fileid)->get();
      $filename = $fileUpload[0]->filename;
      $original_filename = $fileUpload[0]->original_filename;
      $file= storage_path(). "/app/".$filename;
      $headers = array(
              'Content-Type: application/xml',
      );

      return response()->download($file, $original_filename, $headers);
    }

    public function generatedXML($filename) {
      $file = storage_path(). "/app/archive/xml/public/".$filename;
      $headers = array(
              'Content-Type: application/xml',
      );

      return response()->download($file, $filename, $headers);
    }

    public function makeThumbnail($filename, $thumbSize=200) {
      $imgSrc = Storage::disk('fullsize')->path($filename);
      $extension = pathinfo($imgSrc)['extension'];
      $extensionLower = strtolower($extension);
      $filenameNoExtension = str_replace($extension, "", $filename);

      $thumbs_path = Storage::disk('thumbnails')->path($filenameNoExtension.$extensionLower);
      $imgDetails = getimagesize($imgSrc);
      $imgMime = $imgDetails['mime'];
      list($width, $height) = getimagesize($imgSrc);

  		if ($width > $height) {
  		  $y = 0;
  		  $x = ($width - $height) / 2;
  		  $smallestSide = $height;
  		}
      else {
  		  $x = 0;
  		  $y = ($height - $width) / 2;
  		  $smallestSide = $width;
  		}

  		// copying the part into thumbnail
  		$thumbWidth = 200;
  		$thumbHeight = 200;
      if (isset($thumbSize)) {
        $thumbWidth = $thumbSize;
        $thumbWidth = $thumbSize;
      }

  		$thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
      if ($imgMime==="image/jpeg") {
        $newImage = imagecreatefromjpeg($imgSrc);
      }
      else if ($imgMime==="image/png") {
        $newImage = imagecreatefrompng($imgSrc);
      }
      else if ($imgMime==="image/gif") {
        $newImage = imagecreatefromgif($imgSrc);
      }

  		imagecopyresampled($thumb, $newImage, 0, 0, $x, $y, $thumbWidth, $thumbHeight, $smallestSide, $smallestSide);

  		imagejpeg($thumb, $thumbs_path, 100);
      imagedestroy( $thumb );
    }

    public function isImage($file) {
      $allowed_extensions = array("jpg", "jpeg", "gif", "png");
      if (in_array(strtolower($file->getClientOriginalExtension()), $allowed_extensions)) {
        return true;
      }
      return false;
    }

}
