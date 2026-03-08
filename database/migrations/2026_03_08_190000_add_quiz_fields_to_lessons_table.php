<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->json('quiz_questions')->nullable()->after('js_code');
            $table->unsignedTinyInteger('quiz_pass_percentage')->nullable()->after('quiz_questions');
            $table->boolean('quiz_randomize_questions')->nullable()->after('quiz_pass_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['quiz_questions', 'quiz_pass_percentage', 'quiz_randomize_questions']);
        });
    }
};
