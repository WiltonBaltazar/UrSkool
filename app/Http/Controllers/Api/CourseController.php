<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\CourseProgressPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Course::query();

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }

        $category = trim((string) $request->query('category', ''));
        if ($category !== '' && $category !== 'All' && $category !== 'Todas') {
            $normalizedCategory = $this->normalizeCategory($category);
            $legacyCategory = $this->legacyCategory($normalizedCategory);

            if ($legacyCategory !== null && $legacyCategory !== $normalizedCategory) {
                $query->whereIn('category', [$normalizedCategory, $legacyCategory]);
            } else {
                $query->where('category', $normalizedCategory);
            }
        }

        $courses = $query->orderByDesc('id')->get();

        return response()->json([
            'data' => $courses->map(fn (Course $course): array => $this->transformCourse($course)),
        ]);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $course->load('sections.lessons');
        $data = $this->transformCourse($course, true);
        $user = $request->user();
        $hasAccess = $this->userHasCourseAccess($user, $course);
        $data['hasAccess'] = $hasAccess;

        if ($user && $hasAccess) {
            $data['progress'] = CourseProgressPresenter::summarize($user, $course);
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    public function access(Request $request, Course $course): JsonResponse
    {
        return response()->json([
            'data' => [
                'hasAccess' => $this->userHasCourseAccess($request->user(), $course),
            ],
        ]);
    }

    /**
     * @throws HttpException
     */
    public function studentShow(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        if (! $this->userHasCourseAccess($user, $course)) {
            abort(403, 'Precisas comprar este curso antes de iniciar as lições.');
        }

        $course->load('sections.lessons');
        $data = $this->transformCourse($course, true);
        if ($user) {
            $data['progress'] = CourseProgressPresenter::summarize($user, $course);
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Course::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn (string $category): string => $this->normalizeCategory($category))
            ->unique()
            ->values();

        return response()->json([
            'data' => ['Todas', ...$categories->all()],
        ]);
    }

    private function transformCourse(Course $course, bool $includeSections = false): array
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
            'sections' => $includeSections
                ? $course->sections->map(fn ($section): array => [
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
                ])->values()
                : [],
        ];
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

    private function normalizeCategory(string $value): string
    {
        return match ($value) {
            'Web Development' => 'Desenvolvimento Web',
            'Web Design' => 'Design Web',
            'UI Design' => 'Design de UI',
            default => $value,
        };
    }

    private function legacyCategory(string $value): ?string
    {
        return match ($value) {
            'Desenvolvimento Web' => 'Web Development',
            'Design Web' => 'Web Design',
            'Design de UI' => 'UI Design',
            default => null,
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
