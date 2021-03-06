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
use App\Entry as Entry;

trait SaveOnDatabaseTrait
{

    public function saveCollateralEntities($entry){

        $single_element= json_decode($entry->element);

        // save topics
        $this->saveTopics($single_element->topics,$entry);

        // save pages
        $this->savePages($single_element->pages,$entry,$single_element->title,$single_element->description);

        // save fulltext
        $this->saveFullText($single_element->pages,$entry);

        // save completeness
        $this->saveCompletenes($single_element->pages,$entry);

        return true;

    }

    private function saveTopics($all_topics,$entry){
      //$clear_entry_topics = $entry->topic()->delete();
      $tp_ids = array();
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

        } elseif (isset($find_topic_id->id)){

            $tp = $find_topic_id;

        } else {

            // Fabiano @TODO LOG THE ERROR TO AN ERROR TABLE AND ASSIGN ONE TOPIC

            $tp = $find_topic_name;

        }
        $tp_ids[]=$tp->id;
        $tp->increment('count');

      }
      $entry->topic()->sync($tp_ids);

      return true;

    }

    private function savePages($all_pages,$entry,$title,$description) {
      $find_page = Pages::where('entry_id',$entry->id)->delete();
      $pgn=0;
      foreach($all_pages as $page) {
        $transcription="";
        $transcription_status=0;
        if (isset($page->transcription_status)) {
          $transcription_status = $page->transcription_status;
        }
        $transcription.= " ".strip_tags($page->transcription);
        $pgn++;


        $pg = new Pages();
        $pg->title = $title;
        $pg->description=$description;
        $pg->text_body=$transcription;
        $pg->page_number=$pgn;
        $pg->entry_id=$entry->id;
        $pg->transcription_status=$transcription_status;
        $pg->save();
      }

    }

    private function saveFullText($all_pages,$entry) {
      $fullText = "";
      foreach ($all_pages as $page) {
        if (isset($page->transcription)) {
          $fullText = " ".strip_tags(trim($page->transcription));
        }
      }
      Entry::where('id','=',$entry->id)->update(['fulltext'=>$fullText]);

      return true;
    }

    private function saveCompletenes($all_pages,$entry) {
      $completed = 0;
      $percentage = 0;
      foreach($all_pages as $page) {
        if (isset($page->transcription_status))
        $status = intval($page->transcription_status);
        if ($status>0) {
          $completed++;
        }
      }
      if ($completed>0) {
        $percentage = ($completed/count($all_pages))*100;
      }
      $entry->completed = $percentage;

      Entry::where('id','=',$entry->id)->update(['completed'=>$percentage]);

      return true;
    }
}
