<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $role_admin = App\Role::where('name','admin')->first();
        $password = Hash::make('secret');

        if(App\User::where('name','administrator')->count()==0){
            $user = App\User::create([
                'name' => 'administrator',
                'email' => 'admin@test.com',
                'password' => $password,
                'remember_token' => str_random(10),
            ]);
            $user->roles()->attach($role_admin);

        }

        $users = factory('App\User', 50)->create();

        App\User::all()->each(function ($user) {
            $user->roles()->attach(App\Role::inRandomOrder()->get());
        });

        $this->command->info('User table seeded!');
    }
}
