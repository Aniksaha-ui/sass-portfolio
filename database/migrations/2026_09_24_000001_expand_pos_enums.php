<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExpandPosEnums extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY payment_method ENUM('card','paypal','bank_transfer','bkash','nagad','cash','bank','mobile','cheque','gift_card','external','pay_later','deposit','points','scan') NOT NULL");
        DB::statement("ALTER TABLE transactions MODIFY status ENUM('success','failed','pending') NOT NULL");
        DB::statement("ALTER TABLE order_tracking MODIFY status ENUM('pending','processing','shipped','delivered','cancelled','_inventory_deducted','_inventory_restored') NOT NULL");
    }

    public function down(): void
    {
        // Existing POS rows can contain the new values; narrowing these enums would destroy their meaning.
    }
}
