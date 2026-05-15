<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_in_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_in_transaction_id')
                ->constrained('stock_in_transactions')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->integer('qty_received');
            $table->integer('qty_available');
            $table->integer('qty_damaged');
            $table->decimal('supplier_cost', 18, 2)
                ->comment('Historical snapshot - never expose via API');
            $table->string('base_margin_type');
            $table->decimal('base_margin_value', 18, 2);
            $table->decimal('base_selling_price', 18, 2);
            $table->text('qc_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_in_items');
    }
};
