<?php

use Faker\Generator as Faker;
use App\User as User;
use App\Topic as Topic;


$factory->define(App\Entry::class, function (Faker $faker) {

    $pages="";

    for($i=0; $i < $faker->randomDigit; $i++){
        $pages = json_encode([
            'archive_filename'=> $faker->macAddress.".jpg",
            'contributor'=>$faker->name,
            'doc_collection_identifier'=>$faker->macAddress,
            'last_rev_timestamp'=>$faker->datetime($max = 'now')->format(DateTime::ATOM),
            'original_filename'=>$faker->macAddress.".jpg",
            'page_count'=> $faker->numberBetween($min = 1, $max = 20),
            'page_id'=> $faker->numberBetween($min = 1000, $max = 9000),
            'rev_ID'=> $faker->numberBetween($min = 1000, $max = 9000),
            'rev_name'=> $faker->name,
            'transcription'=> $faker->randomHtml(2,3)
        ]);

    }

    $topics = array();

    for($i=0; $i < $faker->randomDigit; $i++){

        $topic_id = Topic::inRandomOrder()->first()->id;
        $topic = Topic::where('id',$topic_id)->select('name')->first()->name;

        array_push($topics, array(
            'topic_ID'=> $topic_id,
            'topic_name'=>$topic
        ));

    }

    return [
        'user_id' => User::inRandomOrder()->first(),
        'element' =>json_encode([
                'api_version' => $faker->randomFloat($nbMaxDecimals = 2, $min = 0, $max = 10),
                'collection' => $faker->sentence($nbWords = 3, $variableNbWords = true),
                'copyright_statement' => $faker->text($maxNbChars = 400),
                'creator' => $faker->name,
                'creator_gender' => $faker->randomElement($array = array ('Female', 'Male')),
                'creator_location' => $faker->city,
                'date_created' => $faker->date($format = 'Y-m-d', $max = 'now'),
                'description' => $faker->text($maxNbChars = 1000),
                'doc_collection' => $faker->slug,
                'language' => $faker->languageCode,
                'letter_ID' => $faker->numberBetween($min = 1000, $max = 9000),
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
                'topics'=> json_encode($topics),
                'user_id'=>$faker->randomNumber($nbDigits = NULL, $strict = false),
                'year_of_death_of_author' => $faker->year($max = 'now')
            ])
    ];

});
