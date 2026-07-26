<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProcurementPaymentTracking extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE company_accounts ENGINE=InnoDB');
        DB::statement('ALTER TABLE account_history ENGINE=InnoDB');
        DB::statement('ALTER TABLE account_history MODIFY amount DECIMAL(12,2) NOT NULL');
        DB::statement('ALTER TABLE account_history MODIFY user_account_type VARCHAR(191) NULL, MODIFY user_account_no VARCHAR(191) NULL, MODIFY getaway VARCHAR(50) NOT NULL, MODIFY com_account_no VARCHAR(191) NULL, MODIFY transaction_reference VARCHAR(100) NOT NULL, MODIFY purpose VARCHAR(100) NOT NULL, MODIFY ip_address VARCHAR(45) NULL');
        Schema::table('procurements', function (Blueprint $table) {
            $table->unsignedBigInteger('company_account_id')->nullable()->after('received_by');
            $table->decimal('payment_amount', 12, 2)->nullable()->after('company_account_id');
            $table->string('payment_reference', 100)->nullable()->after('payment_amount');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');
        });
    }
    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) { $table->dropColumn(['company_account_id', 'payment_amount', 'payment_reference', 'paid_at']); });
    }
}
