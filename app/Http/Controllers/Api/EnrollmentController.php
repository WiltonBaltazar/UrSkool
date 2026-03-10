<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    private const QUERY_PERMISSION_FALLBACK_MINUTES = 90;

    private const ACTIVE_PENDING_LOCK_MINUTES = 5;

    public function __construct(
        private readonly MpesaService $mpesaService
    ) {}

    /**
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'courseId' => ['required', 'integer', 'exists:courses,id'],
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'mpesaContact' => ['nullable', 'string', 'regex:/^(?:258)?(?:82|83|84|85|86|87)[0-9]{7}$/'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $course = Course::query()->findOrFail($validated['courseId']);
        $checkoutIdentity = $this->resolveCheckoutIdentity($request, $validated);
        [$checkoutUser, $accountPrepared] = $this->ensureCheckoutUserRecord($checkoutIdentity);

        $otherPendingEnrollment = Enrollment::query()
            ->with('course:id,title')
            ->where('user_id', $checkoutUser->id)
            ->where('status', 'pending')
            ->where('course_id', '!=', $course->id)
            ->latest('updated_at')
            ->first();

        if ($otherPendingEnrollment) {
            if (! $this->canRetryPendingEnrollment($otherPendingEnrollment)) {
                $pendingCourseTitle = (string) ($otherPendingEnrollment->course?->title ?? 'outro curso');

                throw ValidationException::withMessages([
                    'courseId' => "Já tens um pagamento pendente para \"{$pendingCourseTitle}\". Aguarda até ".self::ACTIVE_PENDING_LOCK_MINUTES.' minutos ou conclui esse pagamento antes de comprar outro curso.',
                ]);
            }

            $otherPendingEnrollment->forceFill([
                'status' => 'failed',
            ])->save();
        }

        $isMockMode = (bool) config('mpesa.mock');
        if ($isMockMode && $this->mpesaService->hasLiveConfiguration()) {
            $isMockMode = false;
        }

        $completedEnrollment = Enrollment::query()
            ->where('course_id', $course->id)
            ->where('user_id', $checkoutUser->id)
            ->where('status', 'completed')
            ->latest('updated_at')
            ->first();

        if ($completedEnrollment) {
            [, $accountCreatedFromLogin] = $this->ensureCheckoutUserAndLogin($request, $checkoutIdentity);
            $accountCreated = $accountPrepared || $accountCreatedFromLogin;

            return response()->json([
                'message' => 'Este curso já foi adquirido nesta conta.',
                'data' => [
                    'id' => (string) $completedEnrollment->id,
                    'courseId' => (string) $course->id,
                    'status' => 'completed',
                    'paymentReference' => (string) $completedEnrollment->payment_reference,
                    'accountCreated' => $accountCreated,
                ],
            ]);
        }

        if ((float) $course->price <= 0) {
            $enrollment = $this->upsertEnrollment(
                course: $course,
                user: $checkoutUser,
                fullName: $validated['fullName'],
                status: 'completed',
                paymentReference: 'FREE-'.Str::upper(Str::random(8)),
            );
            [, $accountCreatedFromLogin] = $this->ensureCheckoutUserAndLogin($request, $checkoutIdentity);
            $accountCreated = $accountPrepared || $accountCreatedFromLogin;

            return response()->json([
                'message' => 'Inscrição concluída com sucesso.',
                'data' => [
                    'id' => (string) $enrollment->id,
                    'courseId' => (string) $course->id,
                    'status' => $enrollment->status,
                    'paymentReference' => $enrollment->payment_reference,
                    'accountCreated' => $accountCreated,
                ],
            ]);
        }

        $pendingEnrollment = Enrollment::query()
            ->where('course_id', $course->id)
            ->where('user_id', $checkoutUser->id)
            ->where('status', 'pending')
            ->whereNotNull('payment_reference')
            ->latest('updated_at')
            ->first();

        if ($pendingEnrollment) {
            $pendingResolution = $this->resolvePendingEnrollment(
                $pendingEnrollment,
                $course,
                $validated['fullName'],
                $isMockMode
            );
            if ($pendingResolution instanceof Enrollment) {
                [, $accountCreatedFromLogin] = $this->ensureCheckoutUserAndLogin($request, $checkoutIdentity);
                $accountCreated = $accountPrepared || $accountCreatedFromLogin;

                return response()->json([
                    'message' => 'Inscrição concluída com sucesso.',
                    'data' => [
                        'id' => (string) $pendingResolution->id,
                        'courseId' => (string) $course->id,
                        'status' => $pendingResolution->status,
                        'paymentReference' => $pendingResolution->payment_reference,
                        'accountCreated' => $accountCreated,
                    ],
                ]);
            }

            if ($pendingResolution === 'pending') {
                if ($this->canRetryPendingEnrollment($pendingEnrollment)) {
                    $pendingEnrollment->forceFill([
                        'status' => 'failed',
                    ])->save();
                } else {
                    return response()->json([
                        'message' => 'Pedido de pagamento já enviado. Confirma o PIN no teu telemóvel.',
                        'data' => [
                            'courseId' => (string) $course->id,
                            'status' => 'pending',
                            'paymentReference' => $pendingEnrollment->payment_reference,
                            'accountCreated' => false,
                        ],
                    ], 202);
                }
            }
        }

        if (! isset($validated['mpesaContact']) || trim((string) $validated['mpesaContact']) === '') {
            throw ValidationException::withMessages([
                'mpesaContact' => 'Informa o número M-Pesa para concluir a compra.',
            ]);
        }

        if (! $isMockMode && ! $this->mpesaService->hasLiveConfiguration()) {
            throw ValidationException::withMessages([
                'mpesa' => 'Configuração M-Pesa incompleta. Define API key, public key e service provider code.',
            ]);
        }

        $reference = $this->generateUniquePaymentReference('T');
        $paymentResult = $isMockMode
            ? [
                'success' => true,
                'transaction_id' => $reference,
                'third_party_reference' => $reference,
                'response_code' => 'INS-0',
                'message' => 'Pagamento simulado com sucesso.',
            ]
            : $this->mpesaService->initiatePayment(
                (string) $validated['mpesaContact'],
                (float) $course->price,
                $reference
            );
        $thirdPartyReference = (string) ($paymentResult['third_party_reference'] ?? $reference);

        if (! ($paymentResult['success'] ?? false)) {
            if ($this->isAmbiguousPaymentFailure($paymentResult)) {
                $enrollment = $this->upsertEnrollment(
                    course: $course,
                    user: $checkoutUser,
                    fullName: $validated['fullName'],
                    status: 'pending',
                    paymentReference: $thirdPartyReference,
                );

                return response()->json([
                    'message' => 'Pedido de pagamento enviado. Se já confirmaste o PIN, aguarda alguns minutos.',
                    'data' => [
                        'id' => (string) $enrollment->id,
                        'courseId' => (string) $course->id,
                        'status' => $enrollment->status,
                        'paymentReference' => $enrollment->payment_reference,
                        'accountCreated' => false,
                    ],
                ], 202);
            }

            $this->upsertEnrollment(
                course: $course,
                user: $checkoutUser,
                fullName: $validated['fullName'],
                status: 'failed',
                paymentReference: $thirdPartyReference,
            );

            return response()->json([
                'message' => (string) ($paymentResult['message'] ?? 'Não foi possível processar o pagamento M-Pesa.'),
                'data' => [
                    'courseId' => (string) $course->id,
                    'status' => 'failed',
                    'paymentReference' => $thirdPartyReference,
                    'accountCreated' => false,
                ],
            ], 402);
        }

        $queryReference = (string) ($paymentResult['transaction_id'] ?? $reference);
        $queryResult = $isMockMode
            ? ['success' => true, 'status' => 'COMPLETED']
            : $this->mpesaService->queryTransactionStatus($queryReference, $thirdPartyReference);

        if (($queryResult['success'] ?? false) && $this->mpesaService->isSuccessfulTransactionStatus($queryResult['status'] ?? null)) {
            $enrollment = $this->upsertEnrollment(
                course: $course,
                user: $checkoutUser,
                fullName: $validated['fullName'],
                status: 'completed',
                paymentReference: $thirdPartyReference,
            );
            [, $accountCreatedFromLogin] = $this->ensureCheckoutUserAndLogin($request, $checkoutIdentity);
            $accountCreated = $accountPrepared || $accountCreatedFromLogin;

            return response()->json([
                'message' => 'Inscrição concluída com sucesso.',
                'data' => [
                    'id' => (string) $enrollment->id,
                    'courseId' => (string) $course->id,
                    'status' => $enrollment->status,
                    'paymentReference' => $thirdPartyReference,
                    'accountCreated' => $accountCreated,
                ],
            ]);
        }

        if (($queryResult['success'] ?? false) && $this->mpesaService->isFailedTransactionStatus($queryResult['status'] ?? null)) {
            $this->upsertEnrollment(
                course: $course,
                user: $checkoutUser,
                fullName: $validated['fullName'],
                status: 'failed',
                paymentReference: $thirdPartyReference,
            );

            return response()->json([
                'message' => 'Pagamento recusado ou cancelado no M-Pesa.',
                'data' => [
                    'courseId' => (string) $course->id,
                    'status' => 'failed',
                    'paymentReference' => $thirdPartyReference,
                    'accountCreated' => false,
                ],
            ], 402);
        }

        if ($this->shouldAssumeCompletedFromInitiation($paymentResult, $queryResult)) {
            $enrollment = $this->upsertEnrollment(
                course: $course,
                user: $checkoutUser,
                fullName: $validated['fullName'],
                status: 'completed',
                paymentReference: $thirdPartyReference,
            );
            [, $accountCreatedFromLogin] = $this->ensureCheckoutUserAndLogin($request, $checkoutIdentity);
            $accountCreated = $accountPrepared || $accountCreatedFromLogin;

            return response()->json([
                'message' => 'Pagamento confirmado e inscrição concluída com sucesso.',
                'data' => [
                    'id' => (string) $enrollment->id,
                    'courseId' => (string) $course->id,
                    'status' => $enrollment->status,
                    'paymentReference' => $thirdPartyReference,
                    'accountCreated' => $accountCreated,
                ],
            ]);
        }

        $enrollment = $this->upsertEnrollment(
            course: $course,
            user: $checkoutUser,
            fullName: $validated['fullName'],
            status: 'pending',
            paymentReference: $thirdPartyReference,
        );

        return response()->json([
            'message' => 'Pedido de pagamento enviado. Confirma o PIN no teu telemóvel.',
            'data' => [
                'id' => (string) $enrollment->id,
                'courseId' => (string) $course->id,
                'status' => $enrollment->status,
                'paymentReference' => $enrollment->payment_reference,
                'accountCreated' => false,
            ],
        ], 202);
    }

    /**
     * @throws ValidationException
     */
    private function resolveCheckoutIdentity(Request $request, array $validated): array
    {
        $authenticatedUser = $request->user();
        $fullName = (string) $validated['fullName'];
        $email = strtolower((string) $validated['email']);

        if ($authenticatedUser) {
            if (strcasecmp($authenticatedUser->email, $email) !== 0) {
                throw ValidationException::withMessages([
                    'email' => 'Usa o e-mail da sessão atual ou termina sessão para trocar de conta.',
                ]);
            }

            if ($fullName !== $authenticatedUser->name) {
                $authenticatedUser->forceFill([
                    'name' => $fullName,
                ])->save();
            }

            return [
                'email' => $authenticatedUser->email,
                'fullName' => $fullName,
                'user' => $authenticatedUser->fresh(),
                'createPassword' => null,
            ];
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingUser) {
            $providedPassword = (string) ($validated['password'] ?? '');
            $storedPassword = (string) $existingUser->password;
            $matches = $providedPassword !== '' && (
                Hash::check($providedPassword, $storedPassword)
                || hash_equals($storedPassword, $providedPassword)
            );

            if (! $matches) {
                throw ValidationException::withMessages([
                    'password' => 'Conta existente. Informa a palavra-passe correta para continuar.',
                ]);
            }

            // Repair legacy plain-text passwords created during checkout.
            if (hash_equals($storedPassword, $providedPassword)) {
                $existingUser->forceFill([
                    'password' => Hash::make($providedPassword),
                ])->save();
            }

            return [
                'email' => $existingUser->email,
                'fullName' => $fullName,
                'user' => $existingUser->fresh(),
                'createPassword' => null,
            ];
        }

        if (! isset($validated['password']) || trim((string) $validated['password']) === '') {
            throw ValidationException::withMessages([
                'password' => 'Define uma palavra-passe para criar a conta automaticamente.',
            ]);
        }

        return [
            'email' => $email,
            'fullName' => $fullName,
            'user' => null,
            'createPassword' => (string) $validated['password'],
        ];
    }

    private function ensureCheckoutUserAndLogin(Request $request, array $checkoutIdentity): array
    {
        [$user, $accountCreated] = $this->ensureCheckoutUserRecord($checkoutIdentity);
        $fullName = (string) $checkoutIdentity['fullName'];
        $existingSessionUser = $request->user();

        if ($existingSessionUser && (int) $existingSessionUser->id === (int) $user->id) {
            if ($fullName !== $existingSessionUser->name) {
                $existingSessionUser->forceFill([
                    'name' => $fullName,
                ])->save();
            }

            return [$existingSessionUser->fresh(), $accountCreated];
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return [$user->fresh(), $accountCreated];
    }

    private function ensureCheckoutUserRecord(array $checkoutIdentity): array
    {
        $fullName = (string) $checkoutIdentity['fullName'];
        $email = (string) $checkoutIdentity['email'];
        $user = $checkoutIdentity['user'] ?? null;
        $accountCreated = false;

        if (! $user instanceof User) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $fullName,
                    'password' => Hash::make((string) ($checkoutIdentity['createPassword'] ?? Str::random(32))),
                    'is_admin' => false,
                ]
            );

            $accountCreated = $user->wasRecentlyCreated;
        }

        if ($fullName !== $user->name) {
            $user->forceFill([
                'name' => $fullName,
            ])->save();
        }

        return [$user->fresh(), $accountCreated];
    }

    private function upsertEnrollment(
        Course $course,
        User $user,
        string $fullName,
        string $status,
        string $paymentReference
    ): Enrollment {
        $enrollment = Enrollment::query()->firstOrNew([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        $previousStatus = $enrollment->exists ? (string) $enrollment->status : null;

        $enrollment->fill([
            'user_id' => $user->id,
            'email' => $user->email,
            'full_name' => $fullName,
            'amount' => (float) $course->price,
            'status' => $status,
            'payment_reference' => $paymentReference,
        ]);
        $enrollment->save();

        if ($status === 'completed' && $previousStatus !== 'completed') {
            $course->increment('student_count');
        }

        return $enrollment;
    }

    private function isAmbiguousPaymentFailure(array $paymentResult): bool
    {
        $responseCode = $paymentResult['response_code'] ?? null;
        if (is_string($responseCode) && $this->mpesaService->isTimeoutResponseCode($responseCode)) {
            return true;
        }

        $message = strtoupper((string) ($paymentResult['message'] ?? ''));
        $technicalMessage = strtoupper((string) ($paymentResult['technical_message'] ?? ''));

        return
            str_contains($message, 'INS-9')
            || str_contains($message, 'TIMEOUT')
            || str_contains($technicalMessage, 'INS-9')
            || str_contains($technicalMessage, 'TIMEOUT')
            || str_contains($technicalMessage, 'CURL ERROR 28');
    }

    private function resolvePendingEnrollment(
        Enrollment $enrollment,
        Course $course,
        string $fullName,
        bool $isMockMode
    ): Enrollment|string|null {
        if ($isMockMode) {
            return $this->markEnrollmentCompleted($enrollment, $course, $fullName);
        }

        $reference = (string) $enrollment->payment_reference;
        $queryResult = $this->mpesaService->queryTransactionStatus($reference, $reference);

        if (($queryResult['success'] ?? false) && $this->mpesaService->isSuccessfulTransactionStatus($queryResult['status'] ?? null)) {
            return $this->markEnrollmentCompleted($enrollment, $course, $fullName);
        }

        if (($queryResult['success'] ?? false) && $this->mpesaService->isFailedTransactionStatus($queryResult['status'] ?? null)) {
            $enrollment->forceFill([
                'status' => 'failed',
            ])->save();

            return null;
        }

        if (
            $this->isQueryPermissionDenied($queryResult)
            && $enrollment->updated_at
            && $enrollment->updated_at->greaterThan(now()->subMinutes(self::QUERY_PERMISSION_FALLBACK_MINUTES))
        ) {
            return $this->markEnrollmentCompleted($enrollment, $course, $fullName);
        }

        // Keep pending enrollments in "pending" state while status is unknown to avoid duplicate charges.
        return 'pending';
    }

    private function shouldAssumeCompletedFromInitiation(array $paymentResult, array $queryResult): bool
    {
        if (! ($paymentResult['success'] ?? false)) {
            return false;
        }

        if (! $this->mpesaService->isSuccessfulResponseCode((string) ($paymentResult['response_code'] ?? null))) {
            return false;
        }

        return $this->isQueryPermissionDenied($queryResult);
    }

    private function isQueryPermissionDenied(array $queryResult): bool
    {
        $technical = strtoupper((string) ($queryResult['technical_message'] ?? ''));
        $message = strtoupper((string) ($queryResult['message'] ?? ''));

        if (
            str_contains($technical, 'HTTP 403')
            || str_contains($message, 'HTTP 403')
            || str_contains($message, 'FORBIDDEN')
        ) {
            return true;
        }

        return false;
    }

    private function markEnrollmentCompleted(Enrollment $enrollment, Course $course, string $fullName): Enrollment
    {
        $previousStatus = (string) $enrollment->status;
        $enrollment->forceFill([
            'full_name' => $fullName,
            'status' => 'completed',
        ])->save();

        if ($previousStatus !== 'completed') {
            $course->increment('student_count');
        }

        return $enrollment;
    }

    private function canRetryPendingEnrollment(Enrollment $enrollment): bool
    {
        if (! $enrollment->updated_at) {
            // Legacy/invalid pending rows without timestamps should not block checkout forever.
            return true;
        }

        return $enrollment->updated_at->lessThanOrEqualTo(
            now()->subMinutes(self::ACTIVE_PENDING_LOCK_MINUTES)
        );
    }

    private function generateUniquePaymentReference(string $prefix): string
    {
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt += 1) {
            $reference = strtoupper($prefix).Str::upper(Str::random(7));

            $exists = Enrollment::query()
                ->where('payment_reference', $reference)
                ->exists();

            if (! $exists) {
                return $reference;
            }
        }

        return strtoupper($prefix).Str::upper(Str::random(7));
    }
}
