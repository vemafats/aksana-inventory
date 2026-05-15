<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sales_number')->unique();
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('employee_id')->constrained('employees');
            $table->timestamp('transaction_date');
            $table->decimal('subtotal_amount', 18, 2);
            $table->decimal('item_discount_amount', 18, 2)->default(0);
            $table->decimal('total_after_item_discount', 18, 2);
            $table->string('transaction_discount_type')->default('none');
            $table->decimal('transaction_discount_value', 18, 2)->default(0);
            $table->decimal('transaction_discount_amount', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2);
            $table->string('payment_method');
            $table->text('note')->nullable();
            $table->uuid('photo_id')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transactions');
    }
};
