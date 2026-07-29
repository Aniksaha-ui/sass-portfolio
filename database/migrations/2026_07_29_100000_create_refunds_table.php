<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundsTable extends Migration
{
    public function up()
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('return_id')->unique();
            $table->integer('order_id');
            $table->integer('user_id');
            $table->decimal('amount', 10, 2);
            $table->string('status', 30)->default('processed');
            $table->string('refund_reference', 100)->unique();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->foreign('return_id')->references('id')->on('returns')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('refunds');
    }
}
