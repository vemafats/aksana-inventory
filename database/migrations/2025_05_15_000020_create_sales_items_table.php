<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_transaction_id')
                ->constrained('sales_transactions')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->integer('qty');
            $table->decimal('supplier_cost_snapshot', 18, 2)
                ->comment('Historical snapshot - never expose via API');
            $table->decimal('base_selling_price_snapshot', 18, 2);
            $table->decimal('bazar_selling_price_snapshot', 18, 2);
            $table->decimal('selling_price', 18, 2);
            $table->decimal('subtotal', 18, 2);
            $table->string('item_discount_type')->default('none');
            $table->decimal('item_discount_value', 18, 2)->default(0);
            $table->decimal('item_discount_amount', 18, 2)->default(0);
            $table->decimal('total_after_discount', 18, 2);
            $table->decimal('gross_profit', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_items');
    }
};
