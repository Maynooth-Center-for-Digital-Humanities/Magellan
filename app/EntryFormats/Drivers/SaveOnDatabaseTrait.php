<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 10/02/2018
 * Time: 06:56
 */

namespace App\EntryFormats\Drivers;

use App\Topic as Topic;

trait SaveOnDatabaseTrait
{
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

                $tp = $find_topic_name;

            }

            $entry->topic()->attach($tp->id);
            $tp->increment('count');

        }

        return true;

    }
}