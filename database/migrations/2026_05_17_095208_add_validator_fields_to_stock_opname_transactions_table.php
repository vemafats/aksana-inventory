<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opname_transactions', function (Blueprint $table) {
            $table->string('validation_status')->default('draft')->after('status');
            $table->uuid('validator_id')->nullable()->after('validation_status');
            $table->foreign('validator_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validator_id');
            $table->text('rejection_note')->nullable()->after('validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_transactions', function (Blueprint $table) {
            $table->dropForeign(['validator_id']);
            $table->dropColumn(['validation_status', 'validator_id', 'validated_at', 'rejection_note']);
        });
    }
};
