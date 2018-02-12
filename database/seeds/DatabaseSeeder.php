<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $datetime1 = new DateTime();
        $this->command->info('Seeding started @ '.$datetime1->format('H:i:s'));
        $this->call(RoleTableSeeder::class);
        $this->call(UsersTableSeeder::class);
        $this->call(TopicTableSeeder::class);
        $this->call(EntryTableSeeder::class);
        $datetime2 = new DateTime();
        $this->command->info('Seeding finished after '.$datetime1->diff($datetime2)->format('%i minutes and %s seconds').' at '.$datetime2->format('H:i:s'));

    }
}
