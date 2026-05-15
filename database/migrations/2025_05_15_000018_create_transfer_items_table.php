<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_transaction_id')
                ->constrained('transfer_transactions')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->integer('qty');
            $table->decimal('supplier_cost_snapshot', 18, 2)
                ->comment('Historical snapshot - never expose via API');
            $table->string('base_margin_type_snapshot')->nullable();
            $table->decimal('base_margin_value_snapshot', 18, 2)->default(0);
            $table->decimal('base_selling_price_snapshot', 18, 2);
            $table->string('bazar_adjust_type');
            $table->decimal('bazar_adjust_value', 18, 2)->default(0);
            $table->decimal('bazar_selling_price', 18, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
    }
};
