<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->integer("page_number");
            $table->unsignedInteger("entry_id");
            $table->foreign('entry_id')->references('id')->on('entry')->onDelete('cascade');
            $table->string("title",255);
            $table->text("description");
            $table->text("text_body");
        });

        DB::statement("ALTER TABLE pages ADD FULLTEXT full(title, description,text_body)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pages');
    }
}
