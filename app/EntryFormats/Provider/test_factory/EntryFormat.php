<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 18/01/2018
 * Time: 09:02
 */

namespace App\EntryFormats\Provider\test_factory;

use Illuminate\Validation\Rule as Rule;
use App\EntryFormats\EntryFormatInterface as EntryFormatInterface;
use Illuminate\Validation\Validator;

use App\EntryFormats\Helpers\SaveOnDatabaseTrait as SaveOnDatabaseTrait;

class EntryFormat implements EntryFormatInterface
{
    use SaveOnDatabaseTrait;

    protected $spec = [
        'api_version' => 'required|max:255',
        'collection' => 'required|string|max:255',
        'copyright_statement' => 'required|string|max:1500',
        'creator' => 'nullable|string|max:255',
        'creator_gender' => array('Female','Male'),
        'creator_location' => 'string|max:255',
        'date_created' => 'date|date_format:Y-m-d',
        'description' => 'string|max:1500',
        'doc_collection' => 'string|max:255',
        'title' => 'required|string|max:500',
        'language' => 'required|alpha|max:255',
        'document_id' => 'required|integer',
        'modified_timestamp' => 'date|required|date_format:Y-m-d\TH:i:sP',
        'number_pages'=>'required|integer',
        'pages.*.archive_filename'=>'required|max:255',
        'pages.*.contributor'=>'required|max:255',
        'pages.*.doc_collection_identifier'=>'required|max:500',
        'pages.*.last_rev_timestamp'=>'date|required|date_format:Y-m-d\TH:i:sP',
        'pages.*.original_filename'=>'required|max:255',
        'pages.*.page_count'=>'required|integer',
        'pages.*.page_id'=>'required|integer',
        'pages.*.rev_id'=>'integer',
        'pages.*.rev_name'=>'string|max:255',
        'pages.*.transcription'=>'required|max:1500',
        'recipient'=>'required|max:255',
        'recipient_location'=>'max:255',
        'request_time'=>'date|required|date_format:Y-m-d\TH:i:sP',
        'source'=>'required|max:255',
        'terms_of_use'=>'required|max:1',
        'time_zone'=>'required|max:255',
        'topics.*.topic_id'=>'required|integer',
        'topics.*.topic_name'=>'required|max:255',
        'type'=>'required|max:255',
        'user_id'=>'required|max:15',
        'year_of_death_of_author'=>'required|max:4'
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
