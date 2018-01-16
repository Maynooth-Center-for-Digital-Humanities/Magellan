<?php

use Faker\Generator as Faker;

$factory->define(App\Entry::class, function (Faker $faker) {

    return [
        'element' => "{\"name\":\"".$faker->name."\"}"
    ];

});
