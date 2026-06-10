<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
        });

        Schema::table('colors', function (Blueprint $table) {
            // colors sudah punya kolom 'code' untuk warna hex — tambah kolom baru 'item_code'
            $table->string('item_code')->nullable()->after('name');
        });

        Schema::table('sizes', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            // rename size_type → category_id (relasi ke categories)
            $table->foreignUuid('category_id')->nullable()->after('code')->constrained('categories')->nullOnDelete();
        });

        Schema::table('product_models', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::table('colors', function (Blueprint $table) {
            $table->dropColumn('item_code');
        });

        Schema::table('sizes', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::table('product_models', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
