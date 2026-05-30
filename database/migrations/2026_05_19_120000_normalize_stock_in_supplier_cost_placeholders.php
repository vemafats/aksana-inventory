<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stock_in_items')
            ->where('supplier_cost', '<=', 1)
            ->update(['supplier_cost' => 0]);
    }

    public function down(): void
    {
        // Legacy placeholder values cannot be restored reliably.
    }
};
