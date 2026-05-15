<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transfer_number')->unique();
            $table->foreignUuid('from_location_id')->constrained('locations');
            $table->foreignUuid('to_location_id')->constrained('locations');
            $table->date('transfer_date');
            $table->string('status')->default('draft');
            $table->text('note')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_transactions');
    }
};
