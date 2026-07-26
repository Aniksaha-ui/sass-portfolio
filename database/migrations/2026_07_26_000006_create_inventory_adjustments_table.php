<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryAdjustmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('inventory_id');
            $table->unsignedInteger('product_id');
            $table->string('warehouse_location', 100);
            $table->integer('previous_quantity')->default(0);
            $table->integer('new_quantity')->default(0);
            $table->integer('adjustment_quantity')->default(0);
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'warehouse_location']);
        });
    }
    public function down(): void { Schema::dropIfExists('inventory_adjustments'); }
}
