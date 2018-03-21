<?php

use Illuminate\Database\Seeder;
use App\Entry;


class EntryTableSeeder extends Seeder
{
    /**
     * Run the database seeds .
     *
     * @return void
     */
    public function run()
    {

        $entry = factory(Entry::class, 0)->create();
        $this->command->info('Entry table seeded!');
    }
}
