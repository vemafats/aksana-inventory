<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignUuid('model_id')->constrained('product_models')->cascadeOnDelete();
            $table->foreignUuid('color_id')->constrained('colors')->cascadeOnDelete();
            $table->foreignUuid('size_id')->constrained('sizes')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->unique();
            $table->string('item_name');
            $table->text('catalog_photo_path')->nullable();
            $table->decimal('latest_supplier_cost', 18, 2)
                ->default(0)
                ->comment('Internal use only - never expose via API');
            $table->string('latest_base_margin_type')->nullable();
            $table->decimal('latest_base_margin_value', 18, 2)->default(0);
            $table->decimal('latest_base_selling_price', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
