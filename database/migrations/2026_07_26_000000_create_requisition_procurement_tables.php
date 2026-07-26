<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequisitionProcurementTables extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('requisitions')) Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number')->unique();
            $table->string('requested_by');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->timestamps();
        });
        if (!Schema::hasTable('requisition_products')) Schema::create('requisition_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            // The legacy products table uses an integer primary key, not Laravel's bigint convention.
            $table->unsignedInteger('product_id')->index();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->unsignedInteger('quantity_received')->default(0);
            $table->timestamps();
            $table->unique(['requisition_id', 'product_id']);
        });
        if (!Schema::hasTable('procurements')) Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->unique()->constrained()->restrictOnDelete();
            $table->string('procurement_number')->unique();
            $table->enum('status', ['pending', 'partial', 'received'])->default('pending');
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();
        });
        if (!Schema::hasTable('stock_receipts')) Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->restrictOnDelete();
            $table->foreignId('requisition_product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('product_id')->index();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('stock_after');
            $table->timestamp('received_at');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('stock_receipts'); Schema::dropIfExists('procurements'); Schema::dropIfExists('requisition_products'); Schema::dropIfExists('requisitions'); }
};
