<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NormalizesCourseAttributes;
use App\Models\Course;
use App\Support\LessonCodeValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    use NormalizesCourseAttributes;
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
            return $this->persistCourse(new Course, $validated);
        });

        $course->load('sections.lessons');
        Cache::put('courses.cache_version', (string) microtime(true));

        return response()->json([
            'message' => 'Curso criado com sucesso.',
            'data' => $this->transformCourse($course),
        ], 201);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $course->load('sections.lessons');
        $managedUrlsBeforeUpdate = $this->collectManagedTextMediaUrls($course);

        $course = DB::transaction(function () use ($course, $validated): Course {
            $course->sections->each(function ($section): void {
                $section->lessons()->forceDelete();
                $section->forceDelete();
            });

            return $this->persistCourse($course, $validated);
        });

        $course->load('sections.lessons');
        $managedUrlsAfterUpdate = $this->collectManagedTextMediaUrls($course);
        $this->deleteManagedTextMediaUrls(array_values(array_diff($managedUrlsBeforeUpdate, $managedUrlsAfterUpdate)));
        Cache::put('courses.cache_version', (string) microtime(true));

        return response()->json([
            'message' => 'Curso atualizado com sucesso.',
            'data' => $this->transformCourse($course),
        ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        // Soft-delete: storage files are preserved in case of restore.
        // The Course::booted() event cascades the soft-delete to sections and lessons.
        $course->delete();
        Cache::put('courses.cache_version', (string) microtime(true));

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
            'sections.*.lessons.*.textMediaType' => ['nullable', Rule::in(['none', 'image', 'youtube'])],
            'sections.*.lessons.*.textMediaImageUrl' => ['nullable', 'string', 'max:2048'],
            'sections.*.lessons.*.textMediaImageFile' => ['nullable', 'file', 'image', 'max:6144'],
            'sections.*.lessons.*.removeTextMediaImage' => ['nullable', 'boolean'],
            'sections.*.lessons.*.textMediaYoutubeUrl' => ['nullable', 'url', 'max:2048'],
            'sections.*.lessons.*.language' => ['nullable', 'string', 'max:40'],
            'sections.*.lessons.*.content' => ['nullable', 'string'],
            'sections.*.lessons.*.starterCode' => ['nullable', 'string'],
            'sections.*.lessons.*.htmlCode' => ['nullable', 'string'],
            'sections.*.lessons.*.cssCode' => ['nullable', 'string'],
            'sections.*.lessons.*.jsCode' => ['nullable', 'string'],
            'sections.*.lessons.*.workspaceFiles' => ['nullable', 'array'],
            'sections.*.lessons.*.workspaceFiles.*.id' => ['required_with:sections.*.lessons.*.workspaceFiles', 'string', 'max:120'],
            'sections.*.lessons.*.workspaceFiles.*.name' => ['required_with:sections.*.lessons.*.workspaceFiles', 'string', 'max:255'],
            'sections.*.lessons.*.workspaceFiles.*.language' => ['required_with:sections.*.lessons.*.workspaceFiles', 'in:html,css,js'],
            'sections.*.lessons.*.workspaceFiles.*.content' => ['nullable', 'string'],
            'sections.*.lessons.*.entryHtmlFileId' => ['nullable', 'string', 'max:120'],
            'sections.*.lessons.*.validationRules' => ['nullable', 'array'],
            'sections.*.lessons.*.validationRules.*.kind' => [
                'required_with:sections.*.lessons.*.validationRules',
                'string',
                Rule::in(LessonCodeValidator::ALLOWED_RULE_KINDS),
            ],
            'sections.*.lessons.*.validationRules.*.value' => ['required_with:sections.*.lessons.*.validationRules', 'string', 'max:500'],
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
                $isCodePracticeLesson = in_array($type, ['code', 'project'], true);
                $validationRules = $isCodePracticeLesson
                    ? $this->resolveValidationRules($lessonPayload)
                    : null;
                $textMedia = $this->resolveTextMediaPayload($lessonPayload, $type);

                $section->lessons()->create([
                    'title' => $lessonPayload['title'],
                    'duration' => $lessonPayload['duration'] ?? null,
                    'video_url' => $textMedia['videoUrl'],
                    'text_media_type' => $textMedia['type'],
                    'text_media_image_url' => $textMedia['imageUrl'],
                    'language' => $lessonPayload['language'] ?? null,
                    'content' => $lessonPayload['content'] ?? null,
                    'starter_code' => $lessonPayload['starterCode'] ?? null,
                    'html_code' => $lessonPayload['htmlCode'] ?? null,
                    'css_code' => $lessonPayload['cssCode'] ?? null,
                    'js_code' => $lessonPayload['jsCode'] ?? null,
                    'workspace_files' => $this->normalizeWorkspaceFiles($lessonPayload),
                    'entry_html_file_id' => $this->normalizeEntryHtmlFileId($lessonPayload),
                    'validation_rules' => $validationRules,
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
                    'textMediaType' => $lesson->text_media_type,
                    'textMediaImageUrl' => $lesson->text_media_image_url,
                    'textMediaYoutubeUrl' => $lesson->type === 'text' ? $lesson->video_url : null,
                    'type' => $lesson->type,
                    'language' => $lesson->language,
                    'content' => $lesson->content,
                    'starterCode' => $lesson->starter_code,
                    'htmlCode' => $lesson->html_code,
                    'cssCode' => $lesson->css_code,
                    'jsCode' => $lesson->js_code,
                    'workspaceFiles' => $lesson->workspace_files,
                    'entryHtmlFileId' => $lesson->entry_html_file_id,
                    'validationRules' => $lesson->validation_rules,
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

    private function normalizeWorkspaceFiles(array $lessonPayload): ?array
    {
        $workspaceFiles = $lessonPayload['workspaceFiles'] ?? null;
        if (! is_array($workspaceFiles)) {
            return null;
        }

        $normalized = [];
        foreach ($workspaceFiles as $file) {
            if (! is_array($file)) {
                continue;
            }

            $id = trim((string) ($file['id'] ?? ''));
            $name = trim((string) ($file['name'] ?? ''));
            $language = trim((string) ($file['language'] ?? ''));

            if ($id === '' || $name === '' || ! in_array($language, ['html', 'css', 'js'], true)) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'language' => $language,
                'content' => (string) ($file['content'] ?? ''),
            ];
        }

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeEntryHtmlFileId(array $lessonPayload): ?string
    {
        $entryId = trim((string) ($lessonPayload['entryHtmlFileId'] ?? ''));
        if ($entryId === '') {
            return null;
        }

        $workspaceFiles = $this->normalizeWorkspaceFiles($lessonPayload) ?? [];
        foreach ($workspaceFiles as $file) {
            if (($file['id'] ?? '') === $entryId && ($file['language'] ?? '') === 'html') {
                return $entryId;
            }
        }

        return null;
    }

    private function normalizeValidationRules(array $lessonPayload): array
    {
        return LessonCodeValidator::normalizeRules($lessonPayload['validationRules'] ?? null);
    }

    private function resolveValidationRules(array $lessonPayload): ?array
    {
        $normalized = $this->normalizeValidationRules($lessonPayload);
        if ($normalized !== []) {
            return $normalized;
        }

        $derived = LessonCodeValidator::deriveValidationRules([
            'html' => (string) ($lessonPayload['htmlCode'] ?? ''),
            'css' => (string) ($lessonPayload['cssCode'] ?? ''),
            'js' => (string) ($lessonPayload['jsCode'] ?? ''),
        ]);

        return $derived === [] ? null : $derived;
    }

    private function resolveTextMediaPayload(array $lessonPayload, string $lessonType): array
    {
        $videoUrl = trim((string) ($lessonPayload['videoUrl'] ?? ''));

        if ($lessonType !== 'text') {
            return [
                'videoUrl' => $videoUrl !== '' ? $videoUrl : null,
                'type' => null,
                'imageUrl' => null,
            ];
        }

        $rawMediaType = trim((string) ($lessonPayload['textMediaType'] ?? ''));
        $mediaType = in_array($rawMediaType, ['none', 'image', 'youtube'], true) ? $rawMediaType : '';

        $imageUrl = trim((string) ($lessonPayload['textMediaImageUrl'] ?? ''));
        $youtubeUrl = trim((string) ($lessonPayload['textMediaYoutubeUrl'] ?? $videoUrl));
        $removeImage = filter_var(
            $lessonPayload['removeTextMediaImage'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($removeImage) {
            $imageUrl = '';
        }

        $imageFile = $lessonPayload['textMediaImageFile'] ?? null;
        if ($imageFile instanceof UploadedFile) {
            $storedPath = $imageFile->store('lessons/text-media', 'public');
            $imageUrl = Storage::disk('public')->url($storedPath);
        }

        if ($mediaType === '') {
            $mediaType = $imageUrl !== ''
                ? 'image'
                : ($youtubeUrl !== '' ? 'youtube' : 'none');
        }

        if ($mediaType === 'none') {
            return [
                'videoUrl' => null,
                'type' => null,
                'imageUrl' => null,
            ];
        }

        if ($mediaType === 'image' && $imageUrl === '') {
            $mediaType = $youtubeUrl !== '' ? 'youtube' : 'none';
        }

        if ($mediaType === 'youtube' && $youtubeUrl === '') {
            $mediaType = $imageUrl !== '' ? 'image' : 'none';
        }

        return [
            'videoUrl' => $mediaType === 'youtube' ? $youtubeUrl : null,
            'type' => $mediaType === 'none' ? null : $mediaType,
            'imageUrl' => $mediaType === 'image' ? $imageUrl : null,
        ];
    }

    private function collectManagedTextMediaUrls(Course $course): array
    {
        return $course->sections
            ->flatMap(fn ($section) => $section->lessons)
            ->pluck('text_media_image_url')
            ->filter(fn ($url): bool => is_string($url) && $url !== '' && $this->isManagedTextMediaUrl($url))
            ->unique()
            ->values()
            ->all();
    }

    private function deleteManagedTextMediaUrls(array $urls): void
    {
        foreach ($urls as $url) {
            $this->deleteManagedTextMediaFile((string) $url);
        }
    }

    private function deleteManagedTextMediaFile(string $url): void
    {
        $parsedPath = parse_url($url, PHP_URL_PATH);
        if (! is_string($parsedPath) || $parsedPath === '') {
            return;
        }

        $storagePrefix = '/storage/';
        if (! str_starts_with($parsedPath, $storagePrefix)) {
            return;
        }

        $relativePath = ltrim(substr($parsedPath, strlen($storagePrefix)), '/');
        if ($relativePath === '' || ! str_starts_with($relativePath, 'lessons/text-media/')) {
            return;
        }

        Storage::disk('public')->delete($relativePath);
    }

    private function isManagedTextMediaUrl(string $url): bool
    {
        $parsedPath = parse_url($url, PHP_URL_PATH);

        return is_string($parsedPath)
            && str_starts_with($parsedPath, '/storage/lessons/text-media/');
    }

}
