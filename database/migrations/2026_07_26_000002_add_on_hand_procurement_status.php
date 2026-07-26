<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddOnHandProcurementStatus extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE procurements MODIFY status ENUM('pending', 'partial', 'received', 'on_hand') NOT NULL DEFAULT 'pending'");
        DB::statement("UPDATE procurements SET status = 'on_hand' WHERE status = 'received'");
    }

    public function down(): void
    {
        DB::statement("UPDATE procurements SET status = 'received' WHERE status = 'on_hand'");
        DB::statement("ALTER TABLE procurements MODIFY status ENUM('pending', 'partial', 'received') NOT NULL DEFAULT 'pending'");
    }
}
