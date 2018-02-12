<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 18/01/2018
 * Time: 09:02
 */

namespace App\EntryFormats\Provider\omeka;

use Illuminate\Validation\Rule as Rule;
use App\EntryFormats\EntryFormatInterface as EntryFormatInterface;

class EntryFormat implements EntryFormatInterface
{
    protected $spec = [
        'api_version' => 'required|string|max:255',
        'collection' => 'required|string|max:255',
        'copyright_statement' => 'required|string|max:1500',
        'creator' => 'nullable|string|max:255',
        'creator_gender' => array('Female','Male'),
        'creator_location' => 'string|max:255',
        'date_created' => 'string|max:255',
        'description' => 'string|max:1500',
        'doc_collection' => 'string|max:255',
        'language' => 'required|alpha|max:255',
        'letter_ID' => 'required|integer',
        'modified_timestamp' => 'date|required|date_format:Y-m-d\TH:i:sP',
        'number_pages'=>'required|integer',
        'pages.*.archive_filename'=>'required|max:255',
        'pages.*.contributor'=>'required|max:255',
        'pages.*.doc_collection_identifier'=>'required|max:500',
        'pages.*.last_rev_timestamp'=>'date|required|date_format:Y-m-d\TH:i:sP',
        'pages.*.original_filename'=>'required|max:255',
        'pages.*.page_count'=>'required|integer',
        'pages.*.page_id'=>'required|integer',
        'pages.*.rev_ID'=>'required|integer',
        'pages.*.rev_name'=>'required|max:255',
        'pages.*.transcription'=>'required|max:1500',
        'recipient'=>'required|max:255',
        'recipient_location'=>'max:255',
        'request_time'=>'date|required|date_format:Y-m-d\TH:i:sP',
        'source'=>'required|max:255',
        'terms_of_use'=>'required|max:1',
        'time_zone'=>'required|max:255',
        'title'=>'required|max:255',
        'topics.*.topic_ID'=>'required|integer',
        'topics.*.topic_name'=>'required|max:255',
        'type'=>'required|max:255',
        'user_id'=>'required|max:15',
        'year_of_death_of_author'=>'required|max:4'
    ];

    public function getValidatorSpec(){

        return array_map(
            function($elem) {return (!is_array($elem) ?$elem:Rule::in($elem));},
            $this->spec
        );
    }
    public function getTextFields($parent){
        return null;
    }
    public function validateText($text,$parent)
    {
        return null;
    }
    public function getConstrainedArrayFields($parent){
        return null;
    }
    public function validateArray($array,$parent){
        return null;
    }

    public function valid($json){
        return null;
    }

}
