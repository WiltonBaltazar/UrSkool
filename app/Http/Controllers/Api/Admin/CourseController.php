<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::query()
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'data' => $courses->map(fn (Course $course): array => [
                'id' => (string) $course->id,
                'title' => $course->title,
                'subtitle' => $course->subtitle,
                'instructor' => $course->instructor,
                'category' => $this->normalizeCategory($course->category),
                'level' => $this->normalizeLevel($course->level),
                'price' => (float) $course->price,
                'studentCount' => (int) $course->student_count,
                'updatedAt' => optional($course->updated_at)->toISOString(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $course = DB::transaction(function () use ($validated): Course {
            return $this->persistCourse(new Course(), $validated);
        });

        $course->load('sections.lessons');

        return response()->json([
            'message' => 'Curso criado com sucesso.',
            'data' => $this->transformCourse($course),
        ], 201);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $course = DB::transaction(function () use ($course, $validated): Course {
            $course->sections()->delete();

            return $this->persistCourse($course, $validated);
        });

        $course->load('sections.lessons');

        return response()->json([
            'message' => 'Curso atualizado com sucesso.',
            'data' => $this->transformCourse($course),
        ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([
            'message' => 'Curso eliminado com sucesso.',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'instructor' => ['required', 'string', 'max:255'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'reviewCount' => ['nullable', 'integer', 'min:0'],
            'studentCount' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'originalPrice' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'url', 'max:2048'],
            'category' => [
                'required',
                'string',
                Rule::in([
                    'Desenvolvimento Web',
                    'JavaScript',
                    'Design Web',
                    'Design de UI',
                    'UX/UI',
                    'Web Development',
                    'Web Design',
                    'UI Design',
                ]),
            ],
            'level' => [
                'required',
                'string',
                Rule::in(['Iniciante', 'Intermediário', 'Avançado', 'Beginner', 'Intermediate', 'Advanced']),
            ],
            'totalHours' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.lessons' => ['nullable', 'array'],
            'sections.*.lessons.*.title' => ['required', 'string', 'max:255'],
            'sections.*.lessons.*.duration' => ['nullable', 'string', 'max:50'],
            'sections.*.lessons.*.videoUrl' => ['nullable', 'url', 'max:2048'],
            'sections.*.lessons.*.language' => ['nullable', 'string', 'max:40'],
            'sections.*.lessons.*.content' => ['nullable', 'string'],
            'sections.*.lessons.*.starterCode' => ['nullable', 'string'],
            'sections.*.lessons.*.htmlCode' => ['nullable', 'string'],
            'sections.*.lessons.*.cssCode' => ['nullable', 'string'],
            'sections.*.lessons.*.jsCode' => ['nullable', 'string'],
            'sections.*.lessons.*.isFree' => ['nullable', 'boolean'],
            'sections.*.lessons.*.type' => ['nullable', 'in:video,text,code,quiz,project'],
            'sections.*.lessons.*.quizQuestions' => ['nullable', 'array'],
            'sections.*.lessons.*.quizQuestions.*.id' => ['nullable', 'string', 'max:120'],
            'sections.*.lessons.*.quizQuestions.*.question' => ['nullable', 'string', 'max:1000'],
            'sections.*.lessons.*.quizQuestions.*.options' => ['nullable', 'array'],
            'sections.*.lessons.*.quizQuestions.*.options.*' => ['nullable', 'string', 'max:500'],
            'sections.*.lessons.*.quizQuestions.*.correctOptionIndex' => ['nullable', 'integer', 'min:0'],
            'sections.*.lessons.*.quizPassPercentage' => ['nullable', 'integer', 'between:1,100'],
            'sections.*.lessons.*.quizRandomizeQuestions' => ['nullable', 'boolean'],
        ], [
            'category.in' => 'Categoria inválida. Escolhe uma das categorias disponíveis.',
            'level.in' => 'Nível inválido. Escolhe Iniciante, Intermediário ou Avançado.',
            'sections.*.lessons.*.type.in' => 'Tipo de lição inválido. Usa vídeo, texto, código, questionário ou projeto.',
            'sections.*.lessons.*.quizPassPercentage.between' => 'A nota mínima do questionário deve estar entre 1 e 100.',
        ]);

        $this->assertQuizLessonsAreValid($validated);

        return $validated;
    }

    private function persistCourse(Course $course, array $payload): Course
    {
        $sections = $payload['sections'] ?? [];
        $totalLessons = 0;

        foreach ($sections as $sectionPayload) {
            $totalLessons += count($sectionPayload['lessons'] ?? []);
        }

        $course->fill([
            'title' => $payload['title'],
            'subtitle' => $payload['subtitle'] ?? '',
            'instructor' => $payload['instructor'],
            'rating' => (float) ($payload['rating'] ?? 0),
            'review_count' => (int) ($payload['reviewCount'] ?? 0),
            'student_count' => (int) ($payload['studentCount'] ?? 0),
            'price' => (float) $payload['price'],
            'original_price' => (float) $payload['originalPrice'],
            'image' => $payload['image'] ?? 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&h=400&fit=crop',
            'category' => $this->normalizeCategory($payload['category']),
            'level' => $this->normalizeLevel($payload['level']),
            'total_hours' => (int) ($payload['totalHours'] ?? 0),
            'total_lessons' => $totalLessons,
            'description' => $payload['description'] ?? '',
        ]);
        $course->save();

        foreach ($sections as $sectionIndex => $sectionPayload) {
            $section = $course->sections()->create([
                'title' => $sectionPayload['title'],
                'sort_order' => $sectionIndex + 1,
            ]);

            foreach (($sectionPayload['lessons'] ?? []) as $lessonIndex => $lessonPayload) {
                $type = $lessonPayload['type'] ?? 'video';
                $isQuizLesson = $type === 'quiz';

                $section->lessons()->create([
                    'title' => $lessonPayload['title'],
                    'duration' => $lessonPayload['duration'] ?? null,
                    'video_url' => $lessonPayload['videoUrl'] ?? null,
                    'is_free' => (bool) ($lessonPayload['isFree'] ?? false),
                    'language' => $lessonPayload['language'] ?? null,
                    'content' => $lessonPayload['content'] ?? null,
                    'starter_code' => $lessonPayload['starterCode'] ?? null,
                    'html_code' => $lessonPayload['htmlCode'] ?? null,
                    'css_code' => $lessonPayload['cssCode'] ?? null,
                    'js_code' => $lessonPayload['jsCode'] ?? null,
                    'quiz_questions' => $isQuizLesson
                        ? $this->normalizeQuizQuestions($lessonPayload['quizQuestions'] ?? [])
                        : null,
                    'quiz_pass_percentage' => $isQuizLesson
                        ? (int) ($lessonPayload['quizPassPercentage'] ?? 80)
                        : null,
                    'quiz_randomize_questions' => $isQuizLesson
                        ? (bool) ($lessonPayload['quizRandomizeQuestions'] ?? true)
                        : null,
                    'type' => $type,
                    'sort_order' => $lessonIndex + 1,
                ]);
            }
        }

        return $course;
    }

    private function transformCourse(Course $course): array
    {
        return [
            'id' => (string) $course->id,
            'title' => $course->title,
            'subtitle' => $course->subtitle,
            'instructor' => $course->instructor,
            'rating' => (float) $course->rating,
            'reviewCount' => (int) $course->review_count,
            'studentCount' => (int) $course->student_count,
            'price' => (float) $course->price,
            'originalPrice' => (float) $course->original_price,
            'image' => $course->image,
            'category' => $this->normalizeCategory($course->category),
            'level' => $this->normalizeLevel($course->level),
            'totalHours' => (int) $course->total_hours,
            'totalLessons' => (int) $course->total_lessons,
            'description' => $course->description,
            'sections' => $course->sections->map(fn ($section): array => [
                'id' => (string) $section->id,
                'title' => $section->title,
                'lessons' => $section->lessons->map(fn ($lesson): array => [
                    'id' => (string) $lesson->id,
                    'title' => $lesson->title,
                    'duration' => $lesson->duration,
                    'videoUrl' => $lesson->video_url,
                    'isFree' => (bool) $lesson->is_free,
                    'type' => $lesson->type,
                    'language' => $lesson->language,
                    'content' => $lesson->content,
                    'starterCode' => $lesson->starter_code,
                    'htmlCode' => $lesson->html_code,
                    'cssCode' => $lesson->css_code,
                    'jsCode' => $lesson->js_code,
                    'quizQuestions' => $lesson->quiz_questions,
                    'quizPassPercentage' => $lesson->quiz_pass_percentage,
                    'quizRandomizeQuestions' => $lesson->quiz_randomize_questions,
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function assertQuizLessonsAreValid(array $payload): void
    {
        $sections = $payload['sections'] ?? [];
        $errors = [];

        foreach ($sections as $sectionIndex => $sectionPayload) {
            foreach (($sectionPayload['lessons'] ?? []) as $lessonIndex => $lessonPayload) {
                if (($lessonPayload['type'] ?? 'video') !== 'quiz') {
                    continue;
                }

                $questions = $lessonPayload['quizQuestions'] ?? null;
                $base = 'sections.'.$sectionIndex.'.lessons.'.$lessonIndex;

                if (! is_array($questions) || count($questions) < 1) {
                    $errors[$base.'.quizQuestions'] = 'Lições do tipo questionário precisam de pelo menos 1 pergunta.';
                    continue;
                }

                foreach ($questions as $questionIndex => $questionPayload) {
                    $questionText = trim((string) ($questionPayload['question'] ?? ''));
                    $options = $questionPayload['options'] ?? null;
                    $correctOptionIndex = filter_var(
                        $questionPayload['correctOptionIndex'] ?? null,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 0]]
                    );
                    $questionBase = $base.'.quizQuestions.'.$questionIndex;

                    if ($questionText === '') {
                        $errors[$questionBase.'.question'] = 'Cada pergunta do questionário deve ter enunciado.';
                    }

                    if (! is_array($options) || count($options) < 2) {
                        $errors[$questionBase.'.options'] = 'Cada pergunta do questionário precisa de pelo menos 2 opções.';
                        continue;
                    }

                    $nonEmptyOptions = array_values(
                        array_filter(
                            array_map(fn ($option): string => trim((string) $option), $options),
                            fn (string $option): bool => $option !== ''
                        )
                    );

                    if (count($nonEmptyOptions) < 2) {
                        $errors[$questionBase.'.options'] = 'As opções da pergunta não podem estar vazias.';
                    }

                    if (
                        $correctOptionIndex === false
                        || $correctOptionIndex >= count($options)
                    ) {
                        $errors[$questionBase.'.correctOptionIndex'] = 'Define uma resposta correta válida para cada pergunta.';
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizeQuizQuestions(array $questions): array
    {
        return array_values(array_map(function (array $question, int $index): array {
            $options = array_values(array_map(
                fn ($option): string => trim((string) $option),
                (array) ($question['options'] ?? [])
            ));

            $questionId = trim((string) ($question['id'] ?? ''));
            $correctOptionIndex = (int) ($question['correctOptionIndex'] ?? 0);
            $maxCorrectIndex = max(0, count($options) - 1);

            return [
                'id' => $questionId !== '' ? $questionId : 'q'.($index + 1),
                'question' => trim((string) ($question['question'] ?? '')),
                'options' => $options,
                'correctOptionIndex' => max(0, min($correctOptionIndex, $maxCorrectIndex)),
            ];
        }, $questions, array_keys($questions)));
    }

    private function normalizeCategory(string $value): string
    {
        return match ($value) {
            'Web Development' => 'Desenvolvimento Web',
            'Web Design' => 'Design Web',
            'UI Design' => 'Design de UI',
            default => $value,
        };
    }

    private function normalizeLevel(string $value): string
    {
        return match ($value) {
            'Beginner' => 'Iniciante',
            'Intermediate' => 'Intermediário',
            'Advanced' => 'Avançado',
            default => $value,
        };
    }
}
