<?php

use Faker\Generator as Faker;
use App\Role;

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

    $role_admin = App\Role::where('name','admin')->first();

    if(App\User::where('name','administrator')->count()==0){
            $user = App\User::create([
            'name' => 'administrator',
            'email' => 'admin@test.com',
            'password' => $password,
            'remember_token' => str_random(10),
        ]);
            $user->roles()->attach($role_admin);

    }

    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => str_random(10),
    ];
});
