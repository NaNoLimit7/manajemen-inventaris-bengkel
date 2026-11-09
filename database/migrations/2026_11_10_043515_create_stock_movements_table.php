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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_batch_id')->constrained('stock_batches')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'id']);
            $table->index('stock_document_id');
        });

        DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movements_quantity_not_zero CHECK (quantity <> 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
