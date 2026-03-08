<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $totalCourses = Course::query()->count();
        $totalUsers = User::query()->count();
        $totalEnrollments = Enrollment::query()->count();
        $totalRevenue = (float) Enrollment::query()->sum('amount');
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
