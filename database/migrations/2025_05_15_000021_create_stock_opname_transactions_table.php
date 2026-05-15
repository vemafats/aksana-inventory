<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('opname_number')->unique();
            $table->foreignUuid('location_id')->constrained('locations');
            $table->date('opname_date');
            $table->string('status')->default('draft');
            $table->text('note')->nullable();
            $table->uuid('photo_id')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_transactions');
    }
};
