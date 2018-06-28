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

        if (Role::where('name','=','admin')->first()===null) {
          $role_admin = new Role();
          $role_admin->name = "admin";
          $role_admin->is_admin = 1;
          $role_admin->description = "Super user";
          $role_admin->save();
        }

        if (Role::where('name','=','transcriber')->first()===null) {
          $role_user = new Role();
          $role_user->name = "transcriber";
          $role_user->default = 1;
          $role_user->description = "Registered user with transcribe rights";
          $role_user->save();
        }

        if (Role::where('name','=','robot')->first()===null) {
          $role_admin = new Role();
          $role_admin->name = "robot";
          $role_admin->description = "Users for the API";
          $role_admin->save();
        }

    }
}
