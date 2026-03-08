<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enrollments = Enrollment::query()
            ->with('course:id,title')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $enrollments->map(fn (Enrollment $enrollment): array => [
                'id' => (string) $enrollment->id,
                'courseId' => (string) $enrollment->course_id,
                'courseTitle' => $enrollment->course?->title ?? 'Curso desconhecido',
                'fullName' => $enrollment->full_name,
                'email' => $enrollment->email,
                'amount' => (float) $enrollment->amount,
                'status' => $enrollment->status,
                'createdAt' => optional($enrollment->created_at)->toISOString(),
            ])->values(),
        ]);
    }
}
