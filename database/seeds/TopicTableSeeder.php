<?php

use Illuminate\Database\Seeder;

class TopicTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $entry = factory(App\Topic::class, 10)->create();
        $this->command->info('Topic table seeded!');
    }
}
