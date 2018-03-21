<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 09/02/2018
 * Time: 16:20
 */

use Faker\Generator as Faker;


/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(App\Topic::class, function (Faker $faker) {

    return [
        'name' => $faker->colorName,
        'topic_id'=>$faker->numberBetween($min = 1, $max = 200),
        'parent_id'=>$faker->numberBetween($min = 0, $max = 100),
        'description' => $faker->name,
        'count' => $faker->numberBetween($min = 1, $max = 10),
    ];
});
