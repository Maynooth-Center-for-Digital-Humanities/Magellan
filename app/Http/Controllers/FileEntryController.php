<?php

namespace App\Http\Controllers;

use App\Uploadedfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use App\Entry;

use App\EntryFormats\Factory as EntryFormatFactory;

class FileEntryController extends Controller
{
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


        if($request->hasFile('data') && $request->filled('format')){

            $format=$request->get('format');

            $entry_format = EntryFormatFactory::create($format);

            $file = $request->file('data');
            $extension=$file->getClientOriginalExtension();
            Storage::disk('local')->put($file->getFilename().'.'.$extension,  File::get($file));

            if($entry_format->valid($file)){

                // Store the file
                $saved_file = $this->store($file,$format,Auth::user()->id);
                $entry = new Entry();
                $entry->element = $entry_format->getJsonData();
                $entry->user_id = Auth::user()->id;
                $entry->current_version = Entry::where('current_version', TRUE)->where('element->document_id', $request->document_id)->count() > 0 ? FALSE : TRUE;
                $entry->uploadedfile_id = $saved_file->id;
                $entry->save();

            };

            return new Response("File Uploaded Successfully", 200);

        }

    }


    public function get($filename){

        $entry = Uploadedfile::where('filename', '=', $filename)->firstOrFail();
        $file = Storage::disk('local')->get($entry->filename);

        return (new Response($file, 200))
            ->header('Content-Type', $entry->mime);
    }


    public function store($file, $format,$user){

        $entry = new Uploadedfile();
        $entry->mime = $file->getClientMimeType();
        $entry->original_filename = $file->getClientOriginalName();
        $entry->filename = $file->getFilename().'.'.$file->getClientOriginalExtension();
        $entry->format = $format;
        $entry->user_id = $user;
        $entry->filesize = $file->getClientSize();
        $entry->save();

        return $entry;

    }



}
