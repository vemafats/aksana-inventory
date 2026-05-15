<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_opname_transaction_id')
                ->constrained('stock_opname_transactions')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->integer('system_available_qty');
            $table->integer('physical_available_qty');
            $table->integer('available_difference_qty');
            $table->integer('damaged_qty')->default(0);
            $table->integer('lost_qty')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
