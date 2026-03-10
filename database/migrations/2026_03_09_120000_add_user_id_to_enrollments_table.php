<?php

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('enrollments', 'user_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (! $this->hasIndex('enrollments', 'enrollments_user_id_index')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->index('user_id', 'enrollments_user_id_index');
            });
        }

        Enrollment::query()
            ->whereNull('user_id')
            ->orderBy('id')
            ->chunkById(200, function ($enrollments): void {
                foreach ($enrollments as $enrollment) {
                    $email = strtolower(trim((string) $enrollment->email));
                    $hasValidEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
                    $safeEmail = $hasValidEmail
                        ? $email
                        : sprintf('legacy-enrollment-%d@urskool.local', $enrollment->id);

                    $user = User::query()
                        ->whereRaw('LOWER(email) = ?', [$safeEmail])
                        ->first();

                    if (! $user instanceof User) {
                        $user = User::query()->create([
                            'name' => trim((string) $enrollment->full_name) ?: sprintf('Student %d', $enrollment->id),
                            'email' => $safeEmail,
                            'password' => Hash::make(Str::random(48)),
                            'is_admin' => false,
                        ]);
                    }

                    $enrollment->forceFill([
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ])->save();
                }
            });

        if (! $this->hasIndex('enrollments', 'enrollments_course_id_index')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->index('course_id', 'enrollments_course_id_index');
            });
        }

        if ($this->hasIndex('enrollments', 'enrollments_course_id_email_unique')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropUnique('enrollments_course_id_email_unique');
            });
        }

        if (! $this->hasIndex('enrollments', 'enrollments_course_id_user_id_unique')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->unique(['course_id', 'user_id'], 'enrollments_course_id_user_id_unique');
            });
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $userForeignDeleteRule = $this->foreignDeleteRule('enrollments', 'enrollments_user_id_foreign');
        if ($userForeignDeleteRule === 'SET NULL') {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropForeign('enrollments_user_id_foreign');
            });
        }

        if ($this->columnIsNullable('enrollments', 'user_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
            });
        }

        if (! $this->hasForeignKey('enrollments', 'enrollments_user_id_foreign')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->foreign('user_id', 'enrollments_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasIndex('enrollments', 'enrollments_course_id_user_id_unique')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropUnique('enrollments_course_id_user_id_unique');
            });
        }

        if (! $this->hasIndex('enrollments', 'enrollments_course_id_email_unique')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->unique(['course_id', 'email'], 'enrollments_course_id_email_unique');
            });
        }

        if (Schema::hasColumn('enrollments', 'user_id')) {
            if ($this->hasForeignKey('enrollments', 'enrollments_user_id_foreign')) {
                Schema::table('enrollments', function (Blueprint $table) {
                    $table->dropForeign('enrollments_user_id_foreign');
                });
            }

            if ($this->hasIndex('enrollments', 'enrollments_user_id_index')) {
                Schema::table('enrollments', function (Blueprint $table) {
                    $table->dropIndex('enrollments_user_id_index');
                });
            }

            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $indexes = DB::select(sprintf('PRAGMA index_list("%s")', $table));

            foreach ($indexes as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $columns = DB::select(sprintf('PRAGMA table_info("%s")', $table));

            foreach ($columns as $row) {
                if (($row->name ?? null) === $column) {
                    return ((int) ($row->notnull ?? 0)) === 0;
                }
            }

            return false;
        }

        $isNullable = DB::table('information_schema.columns')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->value('is_nullable');

        return strtoupper((string) $isNullable) === 'YES';
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        return DB::table('information_schema.table_constraints')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('constraint_type', 'FOREIGN KEY')
            ->where('constraint_name', $constraint)
            ->exists();
    }

    private function foreignDeleteRule(string $table, string $constraint): ?string
    {
        if (DB::getDriverName() === 'sqlite') {
            $foreignKeys = DB::select(sprintf('PRAGMA foreign_key_list("%s")', $table));

            foreach ($foreignKeys as $row) {
                if (($row->from ?? null) === 'user_id') {
                    return strtoupper((string) ($row->on_delete ?? ''));
                }
            }

            return null;
        }

        $rule = DB::table('information_schema.referential_constraints')
            ->whereRaw('constraint_schema = database()')
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->value('delete_rule');

        return $rule ? strtoupper((string) $rule) : null;
    }
};
