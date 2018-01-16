<?php

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

$factory->define(App\User::class, function (Faker $faker) {
    static $password;


    $password = Hash::make('secret');

    if(App\User::where('name','administrator')->count()==0){
            User::create([
            'name' => 'administrator',
            'email' => 'admin@test.com',
            'password' => $password,
            'remember_token' => str_random(10)
        ]);
    }

    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => str_random(10),
    ];
});
