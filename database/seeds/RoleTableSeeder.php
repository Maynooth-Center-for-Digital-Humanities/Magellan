<?php

use Illuminate\Database\Seeder;
use App\Role;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $role_admin = new Role();
        $role_admin->name = "admin";
        $role_admin->description = "Super user";
        $role_admin->save();

        $role_user = new Role();
        $role_user->name = "user";
        $role_user->description = "Use of the web interface";
        $role_user->save();

        $role_admin = new Role();
        $role_admin->name = "robot";
        $role_admin->description = "Users for the API";
        $role_admin->save();

    }
}
