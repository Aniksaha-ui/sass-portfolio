<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWarehouseToStockReceipts extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->string('warehouse_location', 100)->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) { $table->dropColumn('warehouse_location'); });
    }
}
