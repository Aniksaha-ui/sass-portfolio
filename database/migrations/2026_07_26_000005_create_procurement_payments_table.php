<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcurementPaymentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('company_account_id');
            $table->decimal('amount', 12, 2);
            $table->string('payment_reference', 100)->unique();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('procurement_payments'); }
}
