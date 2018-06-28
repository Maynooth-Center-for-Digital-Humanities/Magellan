<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEntryTranscriptionStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('entry', function (Blueprint $table) {
          //
          $table->boolean('transcription_status')->default('-1')->comment('-1:not available for transcription;0:open for transcription;1:completed;2:approved');
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
          //
          $table->dropColumn(['transcription_status']);
      });
    }
}
