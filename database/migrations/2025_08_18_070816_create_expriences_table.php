<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExpriencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('expriences', function (Blueprint $table) {
            $table->id();
            $table->string('job_title');
            $table->string('company');
            $table->string('location');
            $table->string('description');
            $table->string('start_date');
            $table->string('end_date');
            $table->string('is_current');
            $table->string('image_url')->nullable();
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('expriences');
    }
}
