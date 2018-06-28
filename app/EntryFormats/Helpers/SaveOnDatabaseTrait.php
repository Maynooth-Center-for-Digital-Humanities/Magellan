<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 10/02/2018
 * Time: 06:56
 */

namespace App\EntryFormats\Helpers;

use App\Topic as Topic;
use App\Pages as Pages;

trait SaveOnDatabaseTrait
{

    public function saveCollateralEntities($entry){

        $single_element= json_decode($entry->element);

        $this->saveTopics($single_element->topics,$entry);

        $this->savePages($single_element->pages,$entry,$single_element->title,$single_element->description);

        return true;

    }

    private function savePages($all_pages,$entry,$title,$description){

        $transcription="";
        $transcription_status=0;
        if (isset($page->transcription_status)) {
          $transcription_status = $page->transcription_status;
        }
        $pgn=0;
        foreach($all_pages as $page){
            $transcription.= " ".strip_tags($page->transcription);
            $pgn++;
        }

        $find_page = Pages::where('entry_id',$entry->id)->delete();

        $pg = new Pages();
        $pg->title = $title;
        $pg->description=$description;
        $pg->text_body=$transcription;
        $pg->page_number=$pgn;
        $pg->entry_id=$entry->id;
        $pg->transcription_status=$transcription_status;
        $pg->save();

    }

    private function saveTopics($all_topics,$entry){
        foreach($all_topics as $topic){

            $find_topic_name = Topic::where('topic_id',$topic->topic_id)->first();
            $find_topic_id = Topic::where('name',$topic->topic_name)->first();

            $equally_null = (($find_topic_name == $find_topic_id) and is_null($find_topic_id) ? true : false );


            if($equally_null){

                $tp = new Topic();
                $tp->name = $topic->topic_name;
                $tp->topic_id = empty($topic->topic_id)? null:$topic->topic_id;
                $tp->description = "";
                $tp->count = 1;
                $tp->save();

            }elseif (isset($find_topic_id->id)){

                $tp = $find_topic_id;

            }else{

                // Fabiano @TODO LOG THE ERROR TO AN ERROR TABLE AND ASSIGN ONE TOPIC

                $tp = $find_topic_name;

            }

            $entry->topic()->attach($tp->id);
            $tp->increment('count');

        }

        return true;

    }
}
