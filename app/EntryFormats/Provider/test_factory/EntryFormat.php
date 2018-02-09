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
use App\Topic as Topic;

class EntryFormat implements EntryFormatInterface
{
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

    public function saveCollateralEntities($entry){


        $single_element = json_decode($entry->element);

        $all_topics=json_decode($single_element->topics);

        foreach($all_topics as $topic){

            $find_topic_name = Topic::where('topic_id',$topic->topic_ID)->first();
            $find_topic_ID = Topic::where('name',$topic->topic_name)->first();

            $equally_null = (($find_topic_name = $find_topic_ID) and is_null($find_topic_ID) ? true : false );

            if($equally_null){
                $tp = new Topic();
                $tp->name = $topic->name;
                $tp->topic_id = $topic->topic_ID;
                $tp->count = 1;
                $tp->save();

            }elseif ($find_topic_name->id = $find_topic_ID->id){

                $tp = $find_topic_ID;

            }else{

                // Fabiano @TODO LOG THE ERROR TO AN ERROR TABLE AND ASSIGN ONE TOPIC
                return false;
                $tp = $find_topic_name;

            }

            $entry->topic()->attach($tp->id);
            $tp->increment('count');

        }

        return true;

    }

}