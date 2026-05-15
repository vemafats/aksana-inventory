<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('movement_number')->unique();
            $table->string('movement_type');
            $table->foreignUuid('item_id')->constrained('items');
            $table->uuid('from_location_id')->nullable();
            $table->uuid('to_location_id')->nullable();
            $table->string('from_stock_status')->nullable();
            $table->string('to_stock_status')->nullable();
            $table->integer('qty');
            $table->string('reference_type');
            $table->uuid('reference_id');
            $table->text('note')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
