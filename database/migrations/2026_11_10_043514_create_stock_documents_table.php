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
        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('purpose', 30)->nullable();
            $table->date('occurred_on');
            $table->string('reference_number', 60)->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreignId('reverses_document_id')->nullable()->unique()->constrained('stock_documents')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('occurred_on');
            $table->index(['type', 'occurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_documents');
    }
};
