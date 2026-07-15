<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Course;
use App\Models\CourseCertificate;
use App\Support\CourseProgressPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudentCertificateController extends Controller
{
    public function issueOrShow(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Sessão inválida. Inicia sessão novamente.');
        }

        if (! $this->userHasCourseAccess($user, $course)) {
            abort(403, 'Sem acesso a este curso.');
        }

        $progress = CourseProgressPresenter::summarize($user, $course);
        $isCompleted = ($progress['totalLessons'] ?? 0) > 0
            && (int) ($progress['completedLessons'] ?? 0) >= (int) ($progress['totalLessons'] ?? 0);

        if (! $isCompleted) {
            return response()->json([
                'message' => 'Conclui todas as lições para emitir o certificado.',
            ], 422);
        }

        $certificate = CourseCertificate::query()->firstOrCreate(
            [
                'course_id' => $course->id,
                'user_id' => $user->id,
            ],
            [
                'share_code' => $this->generateShareCode(),
                'issued_at' => now(),
            ]
        );

        $certificate->loadMissing('course', 'user');

        return response()->json([
            'data' => $this->transformCertificate($certificate),
        ]);
    }

    public function showPublic(string $shareCode): JsonResponse
    {
        $certificate = CourseCertificate::query()
            ->with(['course', 'user'])
            ->where('share_code', $shareCode)
            ->firstOrFail();

        return response()->json([
            'data' => $this->transformCertificate($certificate),
        ]);
    }

    private function transformCertificate(CourseCertificate $certificate): array
    {
        $settings = AppSetting::query()
            ->pluck('value', 'key')
            ->toArray();

        $schoolName = trim((string) ($settings['certificate_school_name'] ?? $settings['platform_name'] ?? 'UrSkool'));
        $issuerTitle = trim((string) ($settings['certificate_issuer_title'] ?? 'Diretor Executivo'));
        $issuerName = trim((string) ($settings['certificate_issuer_name'] ?? 'Direção Académica'));
        $signatureImageUrl = trim((string) ($settings['certificate_signature_url'] ?? ''));
        $baseUrl = rtrim((string) (config('app.url') ?: url('/')), '/');
        $shareUrl = $baseUrl.'/certificate/'.$certificate->share_code;

        return [
            'id' => (string) $certificate->id,
            'shareCode' => $certificate->share_code,
            'shareUrl' => $shareUrl,
            'recipientName' => $certificate->user?->name ?? 'Estudante',
            'courseId' => (string) ($certificate->course?->id ?? ''),
            'courseTitle' => $certificate->course?->title ?? 'Curso',
            'schoolName' => $schoolName !== '' ? $schoolName : 'UrSkool',
            'issuerTitle' => $issuerTitle !== '' ? $issuerTitle : 'Diretor Executivo',
            'issuerName' => $issuerName !== '' ? $issuerName : 'Direção Académica',
            'signatureImageUrl' => $signatureImageUrl !== '' ? $signatureImageUrl : null,
            'issuedAt' => $certificate->issued_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    private function generateShareCode(): string
    {
        do {
            $code = Str::upper(Str::random(14));
        } while (
            CourseCertificate::query()->where('share_code', $code)->exists()
        );

        return $code;
    }

    private function userHasCourseAccess($user, Course $course): bool
    {
        if ((float) $course->price <= 0) {
            return true;
        }

        return $course->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->exists();
    }
}
