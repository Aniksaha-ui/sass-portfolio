<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRequisitionRequestDetails extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->string('department', 100)->nullable()->after('requested_by');
            $table->string('priority', 20)->default('normal')->after('department');
            $table->date('required_by')->nullable()->after('priority');
            $table->string('supplier_name')->nullable()->after('required_by');
            $table->string('reference_no', 100)->nullable()->after('supplier_name');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['department', 'priority', 'required_by', 'supplier_name', 'reference_no']);
        });
    }
}
