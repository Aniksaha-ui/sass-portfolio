<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosTables extends Migration
{
    public function up(): void
    {
        Schema::create('pos_holds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('cashier_id');
            $table->string('reference', 40)->unique();
            $table->json('cart_data');
            $table->timestamps();
            $table->foreign('cashier_id')->references('id')->on('users');
        });
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('order_id')->unique();
            $table->integer('cashier_id');
            $table->string('customer_name');
            $table->string('customer_phone', 30)->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('cost_total', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('rounding_amount', 12, 2)->default(0);
            $table->decimal('returned_amount', 12, 2)->default(0);
            $table->decimal('returned_cost', 12, 2)->default(0);
            $table->string('coupon_code', 50)->nullable();
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('cashier_id')->references('id')->on('users');
            $table->index('created_at');
        });
        Schema::create('pos_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('order_id');
            $table->string('kind', 10)->default('payment');
            $table->string('method', 30);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index(['order_id', 'kind']);
        });
        Schema::create('pos_coupon_redemptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('coupon_id');
            $table->integer('order_id')->unique();
            $table->timestamp('created_at');
            $table->foreign('coupon_id')->references('id')->on('coupons');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
        Schema::table('returns', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('returns', fn (Blueprint $table) => $table->dropColumn('quantity'));
        Schema::dropIfExists('pos_coupon_redemptions');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('pos_holds');
    }
}
