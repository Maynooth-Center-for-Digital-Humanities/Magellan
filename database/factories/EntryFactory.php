<?php

use Faker\Generator as Faker;
use App\User as User;


$factory->define(App\Entry::class, function (Faker $faker) {

    $pages="";
    $topics="";

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

    for($i=0; $i < $faker->randomDigit; $i++){
        $topics = json_encode([
            'topic_ID'=> $faker->numberBetween($min = 1000, $max = 9000),
            'topic_name'=>$faker->sentence($nbWords = 3, $variableNbWords = true),
        ]);

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
                'topics'=> $topics,
                'type'=>'test_factory',
                'user_id'=>$faker->randomNumber($nbDigits = NULL, $strict = false),
                'year_of_death_of_author' => $faker->year($max = 'now')
            ])
    ];

});
