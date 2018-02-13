<?php

use Faker\Generator as Faker;
use App\User as User;
use App\Topic as Topic;


$factory->define(App\Entry::class, function (Faker $faker) {

    $pages=array();

    for($i=0; $i < $faker->randomDigit; $i++){
            array_push($pages,
                array([
                        'archive_filename'=> $faker->macAddress.".jpg",
                        'contributor'=>$faker->name,
                        'doc_collection_identifier'=>$faker->macAddress,
                        'last_rev_timestamp'=>$faker->datetime($max = 'now')->format(DateTime::ATOM),
                        'original_filename'=>$faker->macAddress.".jpg",
                        'page_count'=> $faker->numberBetween($min = 1, $max = 20),
                        'page_id'=> $faker->numberBetween($min = 1000, $max = 9000),
                        'rev_ID'=> $faker->numberBetween($min = 1000, $max = 9000),
                        'rev_name'=> $faker->name,
                            'transcription'=> $faker->randomHtml(2,2)
                ]));

    }

    $topics_list = Topic::all();

    $topics = array();

    for($i=0; $i < $faker->numberBetween($min = 10, $max = 20); $i++){

        $rnd_number=$faker->numberBetween($min = 0, $max = count($topics_list)-1);

        array_push($topics, array(
            'topic_ID'=> $topics_list[$rnd_number]->topic_id,
            'topic_name'=>$topics_list[$rnd_number]->name
        ));

    }

    // Handle the current version option

    $letter_id = $faker->numberBetween($min = 100, $max = 105);
    $current_version = FALSE;
    return ['user_id' => User::inRandomOrder()->first(),
        'current_version' => $current_version,
        'element'=>json_encode([
                'api_version' => $faker->randomFloat($nbMaxDecimals = 2, $min = 0, $max = 10),
                'collection' => $faker->sentence($nbWords = 3, $variableNbWords = true),
                'title' => $faker->sentence($nbWords = 10, $variableNbWords = true),
                'copyright_statement' => $faker->text($maxNbChars = 400),
                'creator' => $faker->name,
                'creator_gender' => $faker->randomElement($array = array ('Female', 'Male')),
                'creator_location' => $faker->city,
                'date_created' => $faker->date($format = 'Y-m-d', $max = 'now'),
                'description' => $faker->text($maxNbChars = 1000),
                'doc_collection' => $faker->slug,
                'language' => $faker->languageCode,
                'letter_ID' => $letter_id,
                'modified_timestamp' => $faker->datetime($max = 'now')->format(DateTime::ATOM),
                'number_pages'=>$faker->numberBetween($min = 1, $max = 100),
                'pages'=>$pages,
                'recipient'=> $faker->name,
                'recipient_location'=> $faker->address,
                'request_time'=> $faker->datetime($max = 'now')->format(DateTime::ATOM),
                'source'=>$faker->slug,
                'terms_of_use'=> $faker->randomDigitNotNull,
                'time_zone'=> $faker->timezone,
                'type'=>'test_factory',
                'topics'=> $topics,
                'user_id'=>$faker->randomNumber($nbDigits = NULL, $strict = false),
                'year_of_death_of_author' => $faker->year($max = 'now')
            ])];

});
