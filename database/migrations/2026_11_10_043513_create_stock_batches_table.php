<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->date('expiry_date')->nullable();
            $table->date('expiry_key')->storedAs("COALESCE(expiry_date, '9999-12-31')");
            $table->decimal('quantity_remaining', 12, 2)->default(0);
            $table->date('received_on');
            $table->timestamps();

            $table->unique(['item_id', 'expiry_key']);
            $table->index('expiry_date');
        });

        DB::statement(
            'ALTER TABLE stock_batches ADD CONSTRAINT chk_stock_batches_quantity_remaining CHECK (quantity_remaining >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
