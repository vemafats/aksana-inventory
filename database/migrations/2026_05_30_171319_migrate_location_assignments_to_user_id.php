<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_assignments', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('location_assignments', function (Blueprint $table) {
            $table->uuid('employee_id')->nullable()->change();
        });

        $this->migrateEmployeeToUserIds('location_assignments', 'employee_id');
    }

    public function down(): void
    {
        Schema::table('location_assignments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    private function migrateEmployeeToUserIds(string $table, string $employeeColumn): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                UPDATE {$table} t
                SET user_id = u.id
                FROM users u
                JOIN employees e ON LOWER(e.name) = LOWER(u.name)
                WHERE e.id = t.{$employeeColumn}
            ");

            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($employeeColumn)
            ->get([$employeeColumn]);

        foreach ($rows as $row) {
            $employee = DB::table('employees')->where('id', $row->{$employeeColumn})->first();

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
                DB::table($table)
                    ->where($employeeColumn, $row->{$employeeColumn})
                    ->update(['user_id' => $userId]);
            }
        }
    }
};
