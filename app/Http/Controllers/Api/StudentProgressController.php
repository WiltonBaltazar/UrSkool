<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Support\CourseProgressPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentProgressController extends Controller
{
    public function upsert(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Sessão inválida. Inicia sessão novamente.');
        }

        if (! $this->userHasCourseAccess($user, $course)) {
            abort(403, 'Sem acesso a este curso.');
        }

        $lesson->loadMissing('section');
        if ((int) ($lesson->section?->course_id ?? 0) !== (int) $course->id) {
            return response()->json([
                'message' => 'A lição não pertence ao curso informado.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
            'codeIsCorrect' => ['nullable', 'boolean'],
            'quizScore' => ['nullable', 'integer', 'between:0,100'],
            'quizPassed' => ['nullable', 'boolean'],
        ]);

        $status = (string) $validated['status'];
        $codeIsCorrect = (bool) ($validated['codeIsCorrect'] ?? false);
        $quizScore = array_key_exists('quizScore', $validated)
            ? (int) $validated['quizScore']
            : null;
        $quizPassed = (bool) ($validated['quizPassed'] ?? false);

        if ($lesson->type === 'quiz') {
            $requiredPassPercentage = (int) ($lesson->quiz_pass_percentage ?? 80);

            if ($quizScore !== null && $quizScore >= $requiredPassPercentage) {
                $quizPassed = true;
            }

            if (! $quizPassed) {
                $status = 'in_progress';
            }

            $codeIsCorrect = false;
        } elseif ($lesson->type === 'code') {
            if (! $codeIsCorrect) {
                $status = 'in_progress';
            }

            $quizScore = null;
            $quizPassed = false;
        } else {
            $codeIsCorrect = false;
            $quizScore = null;
            $quizPassed = false;
        }

        LessonProgress::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'status' => $status,
                'code_is_correct' => $codeIsCorrect,
                'quiz_score' => $quizScore,
                'quiz_passed' => $quizPassed,
                'completed_at' => $status === 'completed' ? now() : null,
            ]
        );

        return response()->json([
            'data' => CourseProgressPresenter::summarize($user, $course),
        ]);
    }

    private function userHasCourseAccess($user, Course $course): bool
    {
        if ((float) $course->price <= 0) {
            return true;
        }

        if (! $user || ! $user->email) {
            return false;
        }

        return $course->enrollments()
            ->where('email', $user->email)
            ->where('status', 'completed')
            ->exists();
    }
}
