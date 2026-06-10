<?php

use App\Models\Category;
use App\Models\Size;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $sepatuCategory = Category::query()->where('code', 'SEP')->first();
        $kaosCategory = Category::query()->where('code', 'KAO')->first();

        if ($sepatuCategory) {
            Size::query()
                ->where('size_type', 'shoes')
                ->whereNull('category_id')
                ->update(['category_id' => $sepatuCategory->id]);
        }

        if ($kaosCategory) {
            Size::query()
                ->where('size_type', 'clothing')
                ->whereNull('category_id')
                ->update(['category_id' => $kaosCategory->id]);
        }
    }

    public function down(): void
    {
        // Data backfill — no rollback.
    }
};
