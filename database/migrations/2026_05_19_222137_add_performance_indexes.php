<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->index(
                ['item_id', 'location_id', 'stock_status'],
                'idx_stock_balances_item_location_status',
            );
            $table->index(
                ['location_id', 'stock_status'],
                'idx_stock_balances_location_status',
            );
            $table->index(
                ['stock_status', 'qty'],
                'idx_stock_balances_status_qty',
            );
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(
                ['item_id', 'created_at'],
                'idx_stock_movements_item_date',
            );
            $table->index(
                ['reference_type', 'reference_id'],
                'idx_stock_movements_reference',
            );
            $table->index(
                ['movement_type', 'created_at'],
                'idx_stock_movements_type_date',
            );
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->index(
                ['location_id', 'transaction_date'],
                'idx_sales_location_date',
            );
            $table->index(
                ['transaction_date'],
                'idx_sales_transaction_date',
            );
            $table->index(
                ['employee_id', 'transaction_date'],
                'idx_sales_employee_date',
            );
        });

        Schema::table('sales_items', function (Blueprint $table) {
            $table->index(
                ['item_id'],
                'idx_sales_items_item',
            );
            $table->index(
                ['sales_transaction_id'],
                'idx_sales_items_transaction',
            );
        });

        Schema::table('items', function (Blueprint $table) {
            $table->index(
                ['is_active'],
                'idx_items_active',
            );
            $table->index(
                ['category_id', 'brand_id'],
                'idx_items_category_brand',
            );
        });

        Schema::table('transfer_transactions', function (Blueprint $table) {
            $table->index(
                ['from_location_id', 'transfer_date'],
                'idx_transfer_from_date',
            );
            $table->index(
                ['to_location_id', 'transfer_date'],
                'idx_transfer_to_date',
            );
        });

        Schema::table('stock_opname_transactions', function (Blueprint $table) {
            $table->index(
                ['validation_status'],
                'idx_opname_validation_status',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_opname_validation_status');
        });

        Schema::table('transfer_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transfer_from_date');
            $table->dropIndex('idx_transfer_to_date');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('idx_items_active');
            $table->dropIndex('idx_items_category_brand');
        });

        Schema::table('sales_items', function (Blueprint $table) {
            $table->dropIndex('idx_sales_items_item');
            $table->dropIndex('idx_sales_items_transaction');
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_sales_location_date');
            $table->dropIndex('idx_sales_transaction_date');
            $table->dropIndex('idx_sales_employee_date');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_stock_movements_item_date');
            $table->dropIndex('idx_stock_movements_reference');
            $table->dropIndex('idx_stock_movements_type_date');
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropIndex('idx_stock_balances_item_location_status');
            $table->dropIndex('idx_stock_balances_location_status');
            $table->dropIndex('idx_stock_balances_status_qty');
        });
    }
};
