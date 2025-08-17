<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->string('description');
            $table->string('website_link');
            $table->string('frontend_tech');
            $table->string('backend_tech');
            $table->string('database');
            $table->string('github_link');
            $table->string('image')->nullable();
            $table->integer('no_of_developers');
            $table->string('developers_name');
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->boolean('isPublished')->nullable()->default(false);
            $table->string('published_at')->nullable();
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
        Schema::dropIfExists('projects');
    }
}
