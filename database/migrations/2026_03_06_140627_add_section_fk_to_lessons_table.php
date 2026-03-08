<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('lessons') || ! Schema::hasTable('sections')) {
            return;
        }

        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        // MySQL fails if the FK already exists; guard against duplicates.
        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $constraint = DB::selectOne(
                'SELECT CONSTRAINT_NAME
                 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_TYPE = ?
                   AND CONSTRAINT_NAME = ?',
                [$database, 'lessons', 'FOREIGN KEY', 'lessons_section_id_foreign']
            );

            if ($constraint) {
                return;
            }
        }

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('lessons')) {
            return;
        }

        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $constraint = DB::selectOne(
                'SELECT CONSTRAINT_NAME
                 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_TYPE = ?
                   AND CONSTRAINT_NAME = ?',
                [$database, 'lessons', 'FOREIGN KEY', 'lessons_section_id_foreign']
            );

            if (! $constraint) {
                return;
            }
        }

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
        });
    }
};
