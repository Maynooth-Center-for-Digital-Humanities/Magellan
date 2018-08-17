<?php

namespace App\EntryFormats\Provider\uploader;

use Illuminate\Validation\Rule as Rule;
use App\EntryFormats\EntryFormatInterface as EntryFormatInterface;
use Illuminate\Validation\Validator;

use App\EntryFormats\Helpers\SaveOnDatabaseTrait as SaveOnDatabaseTrait;

class EntryFormat implements EntryFormatInterface
{
    use SaveOnDatabaseTrait;

    protected $spec = [
        'api_version' => 'required|string|max:255',
        'collection' => 'string|max:255',
        'collection_id' => 'integer|max:255',
        'copyright_statement' => 'required|string|max:1500',
        'creator' => 'nullable|string|max:255',
        'creator_gender' => array('Female','Male'),
        'creator_location' => 'string|max:255',
        'date_created' => 'string|max:255',
        'description' => 'nullable|string|max:2500',
        'doc_collection' => 'string|max:255',
        'language' => 'required|alpha|max:255',
        'document_id' => 'required|integer',
        'modified_timestamp' => 'date|required|date_format:Y-m-d\TH:i:sP',
        'number_pages'=>'required|integer',
        'title' => 'required|string|max:500',
        'pages.*.archive_filename'=>'required|max:255',
        'pages.*.contributor'=>'nullable|string|max:255',
        'pages.*.last_rev_timestamp'=>'date|required|date_format:Y-m-d\TH:i:sP',
        'pages.*.original_filename'=>'required|max:255',
        'pages.*.page_count'=>'required|integer',
        'pages.*.page_id'=>'integer',
        'pages.*.page_type'=>'nullable|string|max:50',
        'pages.*.rev_id'=>'integer',
        'pages.*.rev_name'=>'string|max:255',
        'pages.*.transcription'=>'string',
        'pages.*.transcription_status'=>'required|integer',
        'recipient'=>'nullable|max:255',
        'recipient_location'=>'max:255',
        'request_time'=>'date|required|date_format:Y-m-d\TH:i:sP',
        'source'=>'required|max:255',
        'terms_of_use'=>'required|max:1',
        'time_zone'=>'required|max:255',
        'topics.*.topic_id'=>'required|integer',
        'topics.*.topic_name'=>'required|max:255',
        'type'=>'required|max:255',
        'user_id'=>'required|max:15',
        'year_of_death_of_author'=>'max:4'
    ];

    private function getValidatorSpec(){

        return array_map(
            function($elem) {return (!is_array($elem) ?$elem:Rule::in($elem));},
            $this->spec
        );
    }
    public function getJsonData(){
        return null;
    }
    public function validateText($text,$parent){
        return null;
    }
    public function getConstrainedArrayFields($parent){

        return null;
    }
    public function validateArray($array,$parent){

        return null;
    }

    public function valid($entry){

         return \Validator::make($entry, $this->getValidatorSpec());

    }

}
