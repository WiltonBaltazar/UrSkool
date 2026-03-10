<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $totalCourses = Course::query()->count();
        $totalUsers = User::query()->count();
        $totalEnrollments = Enrollment::query()->count();
        $totalRevenue = (float) Enrollment::query()->where('status', 'completed')->sum('amount');
        $totalLessons = (int) Course::query()->sum('total_lessons');
        $instructorsCount = Course::query()->distinct('instructor')->count('instructor');

        $courses = Course::query()
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        $users = User::query()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $enrollments = Enrollment::query()
            ->with('course:id,title')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $categories = Course::query()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $courseProgressStats = LessonProgress::query()
            ->selectRaw("course_id, count(distinct user_id) as active_students, sum(case when status = 'completed' then 1 else 0 end) as completed_lessons, avg(quiz_score) as avg_quiz_score, avg(case when quiz_passed = 1 then 1 else 0 end) as quiz_pass_rate")
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        $completedEnrollmentCounts = Enrollment::query()
            ->where('status', 'completed')
            ->selectRaw('course_id, count(*) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id');

        $coursePerformance = Course::query()
            ->orderByDesc('student_count')
            ->limit(20)
            ->get()
            ->map(function (Course $course) use ($courseProgressStats, $completedEnrollmentCounts): array {
                $courseStat = $courseProgressStats->get($course->id);
                $activeStudents = (int) ($courseStat->active_students ?? 0);
                $completedLessons = (int) ($courseStat->completed_lessons ?? 0);
                $totalLessons = max(1, (int) $course->total_lessons);
                $completionRate = $activeStudents > 0
                    ? round(min(100, ($completedLessons / ($activeStudents * $totalLessons)) * 100), 2)
                    : 0.0;
                $quizPassRate = round(((float) ($courseStat->quiz_pass_rate ?? 0)) * 100, 2);

                return [
                    'courseId' => (string) $course->id,
                    'courseTitle' => $course->title,
                    'enrollments' => (int) ($completedEnrollmentCounts->get($course->id) ?? 0),
                    'activeStudents' => $activeStudents,
                    'completionRate' => $completionRate,
                    'averageQuizScore' => round((float) ($courseStat->avg_quiz_score ?? 0), 2),
                    'quizPassRate' => $quizPassRate,
                ];
            })
            ->values();

        $completedEnrollmentStatsByUser = Enrollment::query()
            ->where('status', 'completed')
            ->selectRaw('user_id, count(*) as enrolled_courses')
            ->groupBy('user_id')
            ->pluck('enrolled_courses', 'user_id');

        $courseLessonTotalsByUser = Enrollment::query()
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->where('enrollments.status', 'completed')
            ->selectRaw('enrollments.user_id, coalesce(sum(courses.total_lessons), 0) as total_lessons')
            ->groupBy('enrollments.user_id')
            ->pluck('total_lessons', 'enrollments.user_id');

        $progressStatsByUser = LessonProgress::query()
            ->selectRaw("user_id, sum(case when status = 'completed' then 1 else 0 end) as completed_lessons, avg(quiz_score) as avg_quiz_score, max(updated_at) as last_activity_at")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $studentPerformance = User::query()
            ->whereIn('id', $completedEnrollmentStatsByUser->keys())
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function (User $user) use ($completedEnrollmentStatsByUser, $courseLessonTotalsByUser, $progressStatsByUser): array {
                $enrolledCourses = (int) ($completedEnrollmentStatsByUser->get($user->id) ?? 0);
                $totalLessons = max(1, (int) ($courseLessonTotalsByUser->get($user->id) ?? 0));
                $progressStats = $progressStatsByUser->get($user->id);
                $completedLessons = (int) ($progressStats->completed_lessons ?? 0);
                $completionRate = $enrolledCourses > 0
                    ? round(min(100, ($completedLessons / $totalLessons) * 100), 2)
                    : 0.0;

                $lastActivityAt = $progressStats?->last_activity_at;
                if ($lastActivityAt !== null && ! $lastActivityAt instanceof Carbon) {
                    $lastActivityAt = Carbon::parse((string) $lastActivityAt);
                }

                return [
                    'userId' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'enrolledCourses' => $enrolledCourses,
                    'completionRate' => $completionRate,
                    'averageQuizScore' => round((float) ($progressStats->avg_quiz_score ?? 0), 2),
                    'lastActivityAt' => $lastActivityAt?->toISOString(),
                ];
            })
            ->sortByDesc('completionRate')
            ->values();

        $categoryBreakdown = $categories->reduce(function (array $carry, $row): array {
            $name = $this->normalizeCategory($row->category);
            $carry[$name] = ($carry[$name] ?? 0) + (int) $row->total;

            return $carry;
        }, []);

        $settings = AppSetting::query()
            ->pluck('value', 'key')
            ->toArray();

        return response()->json([
            'data' => [
                'stats' => [
                    'totalCourses' => $totalCourses,
                    'totalUsers' => $totalUsers,
                    'totalEnrollments' => $totalEnrollments,
                    'totalRevenue' => round($totalRevenue, 2),
                    'totalLessons' => $totalLessons,
                    'instructorsCount' => $instructorsCount,
                ],
                'courses' => $courses->map(fn (Course $course): array => [
                    'id' => (string) $course->id,
                    'title' => $course->title,
                    'category' => $this->normalizeCategory($course->category),
                    'level' => $this->normalizeLevel($course->level),
                    'instructor' => $course->instructor,
                    'price' => (float) $course->price,
                    'studentCount' => (int) $course->student_count,
                    'updatedAt' => optional($course->updated_at)->toISOString(),
                ])->values(),
                'users' => $users->map(fn (User $user): array => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isAdmin' => (bool) $user->is_admin,
                    'createdAt' => optional($user->created_at)->toISOString(),
                ])->values(),
                'enrollments' => $enrollments->map(fn (Enrollment $enrollment): array => [
                    'id' => (string) $enrollment->id,
                    'courseId' => (string) $enrollment->course_id,
                    'courseTitle' => $enrollment->course?->title ?? 'Curso desconhecido',
                    'fullName' => $enrollment->full_name,
                    'email' => $enrollment->email,
                    'amount' => (float) $enrollment->amount,
                    'status' => $enrollment->status,
                    'createdAt' => optional($enrollment->created_at)->toISOString(),
                ])->values(),
                'categories' => collect($categoryBreakdown)
                    ->map(fn (int $count, string $name): array => [
                        'name' => $name,
                        'count' => $count,
                    ])
                    ->sortByDesc('count')
                    ->values(),
                'coursePerformance' => $coursePerformance,
                'studentPerformance' => $studentPerformance,
                'settings' => [
                    'platformName' => $settings['platform_name'] ?? 'UrSkool',
                    'supportEmail' => $settings['support_email'] ?? 'support@urskool.test',
                    'currency' => 'MZN',
                    'maintenanceMode' => ($settings['maintenance_mode'] ?? 'false') === 'true',
                    'allowSelfSignup' => ($settings['allow_self_signup'] ?? 'true') === 'true',
                    'defaultCourseVisibility' => $settings['default_course_visibility'] ?? 'public',
                ],
            ],
        ]);
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
