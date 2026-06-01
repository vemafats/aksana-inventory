<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_transactions', function (Blueprint $table) {
            $table->foreignUuid('event_id')
                ->nullable()
                ->after('to_location_id')
                ->constrained('events')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfer_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
