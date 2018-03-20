<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFileuploadedToEntryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('entry', function (Blueprint $table) {
            $table->unsignedInteger('uploadedfile_id')->nullable();
            $table->foreign('uploadedfile_id')->references('id')->on('uploadedfile')->onDelete('set null');

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
            $table->dropForeign('entry_uploadedfile_id_foreign');
            $table->dropColumn('uploadedfile_id');
        });
    }
}
