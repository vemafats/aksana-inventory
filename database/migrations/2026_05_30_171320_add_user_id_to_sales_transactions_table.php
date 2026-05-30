<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('employee_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE sales_transactions st
                SET user_id = u.id
                FROM users u
                JOIN employees e ON LOWER(e.name) = LOWER(u.name)
                WHERE e.id = st.employee_id
            ');
        } else {
            $transactions = DB::table('sales_transactions')
                ->whereNotNull('employee_id')
                ->get(['id', 'employee_id']);

            foreach ($transactions as $transaction) {
                $employee = DB::table('employees')->where('id', $transaction->employee_id)->first();

                if ($employee === null) {
                    continue;
                }

                $userId = DB::table('users')
                    ->whereRaw('LOWER(name) = ?', [strtolower($employee->name)])
                    ->value('id');

                if ($userId === null && $employee->email) {
                    $userId = DB::table('users')
                        ->whereRaw('LOWER(email) = ?', [strtolower($employee->email)])
                        ->value('id');
                }

                if ($userId !== null) {
                    DB::table('sales_transactions')
                        ->where('id', $transaction->id)
                        ->update(['user_id' => $userId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
