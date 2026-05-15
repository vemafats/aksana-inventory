<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_in_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_number')->unique();
            $table->string('supplier_name')->nullable();
            $table->date('transaction_date');
            $table->integer('total_qty_received')->default(0);
            $table->integer('total_qty_available')->default(0);
            $table->integer('total_qty_damaged')->default(0);
            $table->text('note')->nullable();
            $table->uuid('photo_id')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_in_transactions');
    }
};
