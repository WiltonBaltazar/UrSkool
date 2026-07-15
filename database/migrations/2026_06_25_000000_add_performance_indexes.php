<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->index('title');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['title']);
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'course_id']);
        });
    }
};
