<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Support\CourseProgressPresenter;
use App\Support\LessonCodeValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentProgressController extends Controller
{
    public function __construct(private readonly LessonCodeValidator $lessonCodeValidator) {}

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
            'quizAnswers' => ['nullable', 'array'],
            'quizAnswers.*' => ['nullable', 'integer', 'min:0'],
            'submittedCode' => ['nullable', 'array'],
            'submittedCode.html' => ['nullable', 'string'],
            'submittedCode.css' => ['nullable', 'string'],
            'submittedCode.js' => ['nullable', 'string'],
        ]);

        $status = (string) $validated['status'];
        $codeIsCorrect = (bool) ($validated['codeIsCorrect'] ?? false);
        $quizScore = null;
        $quizPassed = false;
        $submittedCode = is_array($validated['submittedCode'] ?? null)
            ? $validated['submittedCode']
            : null;

        if ($lesson->type === 'quiz') {
            $quizAnswers = is_array($validated['quizAnswers'] ?? null) ? $validated['quizAnswers'] : [];
            $questions = is_array($lesson->quiz_questions) ? $lesson->quiz_questions : [];
            $requiredPassPercentage = (int) ($lesson->quiz_pass_percentage ?? 80);

            if (! empty($questions) && ! empty($quizAnswers)) {
                $correctCount = 0;
                foreach ($questions as $question) {
                    $questionId = (string) ($question['id'] ?? '');
                    if ($questionId === '') {
                        continue;
                    }
                    $correctOptionIndex = (int) ($question['correctOptionIndex'] ?? 0);
                    $submittedAnswer = array_key_exists($questionId, $quizAnswers)
                        ? (int) $quizAnswers[$questionId]
                        : -1;
                    if ($submittedAnswer === $correctOptionIndex) {
                        $correctCount++;
                    }
                }
                $totalQuestions = count($questions);
                $quizScore = $totalQuestions > 0
                    ? (int) round(($correctCount / $totalQuestions) * 100)
                    : 0;
                $quizPassed = $quizScore >= $requiredPassPercentage;
            }

            if (! $quizPassed) {
                $status = 'in_progress';
            }

            $codeIsCorrect = false;
        } elseif (in_array($lesson->type, ['code', 'project'], true)) {
            $submittedCodeForValidation = [
                'html' => (string) ($submittedCode['html'] ?? ''),
                'css' => (string) ($submittedCode['css'] ?? ''),
                'js' => (string) ($submittedCode['js'] ?? ''),
            ];
            $baselineCode = $this->resolveLessonBaselineCode($lesson);
            $hasChangedCode = $this->hasCodeChanged($submittedCodeForValidation, $baselineCode);
            $hasHtmlMarkup = trim($submittedCodeForValidation['html']) !== '';

            if (! $hasChangedCode || ! $hasHtmlMarkup) {
                $codeIsCorrect = false;
            } else {
                $evaluation = $this->lessonCodeValidator->validate(
                    $submittedCodeForValidation,
                    $lesson->validation_rules
                );
                $codeIsCorrect = (bool) ($evaluation['passed'] ?? false);
            }

            $status = $codeIsCorrect ? 'completed' : 'in_progress';
        } else {
            $codeIsCorrect = false;
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

        if (! $user || ! $user->id) {
            return false;
        }

        return $course->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->exists();
    }

    private function resolveLessonBaselineCode(Lesson $lesson): array
    {
        $workspaceFiles = is_array($lesson->workspace_files) ? $lesson->workspace_files : [];
        $normalizedFiles = [];

        foreach ($workspaceFiles as $file) {
            if (! is_array($file)) {
                continue;
            }

            $id = trim((string) ($file['id'] ?? ''));
            $name = trim((string) ($file['name'] ?? ''));
            $language = trim((string) ($file['language'] ?? ''));
            if (! in_array($language, ['html', 'css', 'js'], true)) {
                continue;
            }

            $normalizedFiles[] = [
                'id' => $id,
                'name' => $name,
                'language' => $language,
                'content' => (string) ($file['content'] ?? ''),
            ];
        }

        if ($normalizedFiles !== []) {
            $entryHtmlFileId = trim((string) ($lesson->entry_html_file_id ?? ''));
            $htmlFile = null;

            if ($entryHtmlFileId !== '') {
                foreach ($normalizedFiles as $file) {
                    if ($file['language'] === 'html' && $file['id'] === $entryHtmlFileId) {
                        $htmlFile = $file;
                        break;
                    }
                }
            }

            if (! $htmlFile) {
                foreach ($normalizedFiles as $file) {
                    if ($file['language'] === 'html' && strtolower($file['name']) === 'index.html') {
                        $htmlFile = $file;
                        break;
                    }
                }
            }

            if (! $htmlFile) {
                foreach ($normalizedFiles as $file) {
                    if ($file['language'] === 'html') {
                        $htmlFile = $file;
                        break;
                    }
                }
            }

            $cssChunks = [];
            $jsChunks = [];
            foreach ($normalizedFiles as $file) {
                if ($file['language'] === 'css') {
                    $cssChunks[] = $file['content'];
                }

                if ($file['language'] === 'js') {
                    $jsChunks[] = $file['content'];
                }
            }

            return [
                'html' => (string) ($htmlFile['content'] ?? ''),
                'css' => implode("\n\n", array_filter($cssChunks, static fn (string $chunk): bool => $chunk !== '')),
                'js' => implode("\n\n", array_filter($jsChunks, static fn (string $chunk): bool => $chunk !== '')),
            ];
        }

        return [
            'html' => (string) ($lesson->html_code ?? ''),
            'css' => (string) ($lesson->css_code ?? ''),
            'js' => (string) ($lesson->js_code ?? ''),
        ];
    }

    private function hasCodeChanged(array $submittedCode, array $baselineCode): bool
    {
        return $this->normalizeSourceForComparison((string) ($submittedCode['html'] ?? '')) !== $this->normalizeSourceForComparison((string) ($baselineCode['html'] ?? ''))
            || $this->normalizeSourceForComparison((string) ($submittedCode['css'] ?? '')) !== $this->normalizeSourceForComparison((string) ($baselineCode['css'] ?? ''))
            || $this->normalizeSourceForComparison((string) ($submittedCode['js'] ?? '')) !== $this->normalizeSourceForComparison((string) ($baselineCode['js'] ?? ''));
    }

    private function normalizeSourceForComparison(string $value): string
    {
        return trim(str_replace("\r", '', $value));
    }
}
