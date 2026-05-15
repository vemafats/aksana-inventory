<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('stock_status');
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'location_id', 'stock_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
