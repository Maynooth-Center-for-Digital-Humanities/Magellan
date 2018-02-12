<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEntryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('entry', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->json('element');
            $table->unsignedInteger('user_id');
            $table->boolean('current_version');
            $table->foreign('user_id')->references('id')->on('users');
            $table->index('current_version');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('entry', function (Blueprint $table) {
            $table->dropIndex(['current_version']); // Drops index 'current_version'
        });

        Schema::drop('entry');
    }
}
