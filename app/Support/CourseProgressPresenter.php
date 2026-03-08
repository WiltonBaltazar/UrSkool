<?php

namespace App\Support;

use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\User;

class CourseProgressPresenter
{
    public static function summarize(User $user, Course $course): array
    {
        $course->loadMissing('sections.lessons');

        $lessonIds = $course->sections
            ->flatMap(fn ($section) => $section->lessons->pluck('id'))
            ->values();

        $progressByLessonId = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('lesson_id');

        $completedLessonIds = [];
        $lessonEntries = [];

        foreach ($lessonIds as $lessonId) {
            $progress = $progressByLessonId->get($lessonId);
            $status = $progress?->status ?? 'not_started';

            if ($status === 'completed') {
                $completedLessonIds[] = (string) $lessonId;
            }

            $lessonEntries[] = [
                'lessonId' => (string) $lessonId,
                'status' => $status,
                'codeIsCorrect' => (bool) ($progress?->code_is_correct ?? false),
                'quizScore' => $progress?->quiz_score,
                'quizPassed' => (bool) ($progress?->quiz_passed ?? false),
                'completedAt' => $progress?->completed_at?->toISOString(),
                'updatedAt' => $progress?->updated_at?->toISOString(),
            ];
        }

        $totalLessons = max(0, $lessonIds->count());
        $completedLessons = count($completedLessonIds);

        return [
            'completedLessonIds' => $completedLessonIds,
            'completedLessons' => $completedLessons,
            'totalLessons' => $totalLessons,
            'completionPercent' => $totalLessons > 0
                ? (float) round(($completedLessons / $totalLessons) * 100, 2)
                : 0.0,
            'lessons' => $lessonEntries,
        ];
    }
}
