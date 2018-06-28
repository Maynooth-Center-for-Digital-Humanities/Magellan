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
        $initial_rights = App\Rights::create([
                'can_update' => 0,
                'status' => 1,
                'label' => 'License Agreement',
                'text' => '',
            ]);

        $this->command->info('Rights table seeded!');
    }
}
