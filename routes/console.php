<?php

use App\Models\Lesson;
use App\Support\LessonCodeValidator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lessons:backfill-validation-rules', function () {
    $updated = 0;

    Lesson::query()
        ->whereIn('type', ['code', 'project'])
        ->whereNull('validation_rules')
        ->chunkById(200, function ($lessons) use (&$updated): void {
            foreach ($lessons as $lesson) {
                $derived = LessonCodeValidator::deriveValidationRules([
                    'html' => (string) ($lesson->html_code ?? ''),
                    'css' => (string) ($lesson->css_code ?? ''),
                    'js' => (string) ($lesson->js_code ?? ''),
                ]);

                if ($derived === []) {
                    continue;
                }

                $lesson->validation_rules = $derived;
                $lesson->save();
                $updated++;
            }
        });

    $this->info("Validation rules backfilled for {$updated} lesson(s).");
})->purpose('Backfill validation rules for code practice lessons');
