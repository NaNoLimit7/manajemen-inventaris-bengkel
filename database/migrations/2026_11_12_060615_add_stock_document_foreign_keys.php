<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_documents', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['purchase_order_id']);
        });
    }
};
