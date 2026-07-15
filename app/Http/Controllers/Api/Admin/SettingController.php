<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = AppSetting::query()
            ->pluck('value', 'key')
            ->toArray();

        return response()->json([
            'data' => [
                'platformName' => $settings['platform_name'] ?? 'UrSkool',
                'supportEmail' => $settings['support_email'] ?? 'support@urskool.test',
                'currency' => 'MZN',
                'maintenanceMode' => ($settings['maintenance_mode'] ?? 'false') === 'true',
                'allowSelfSignup' => ($settings['allow_self_signup'] ?? 'true') === 'true',
                'defaultCourseVisibility' => $settings['default_course_visibility'] ?? 'public',
                'certificateSchoolName' => $settings['certificate_school_name'] ?? ($settings['platform_name'] ?? 'UrSkool'),
                'certificateIssuerTitle' => $settings['certificate_issuer_title'] ?? 'Diretor Executivo',
                'certificateIssuerName' => $settings['certificate_issuer_name'] ?? 'Direção Académica',
                'certificateSignatureUrl' => $settings['certificate_signature_url'] ?? '',
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platformName' => ['required', 'string', 'max:255'],
            'supportEmail' => ['required', 'email', 'max:255'],
            'currency' => ['required', 'string', 'in:MZN'],
            'maintenanceMode' => ['required', 'boolean'],
            'allowSelfSignup' => ['required', 'boolean'],
            'defaultCourseVisibility' => ['required', 'in:public,private'],
            'certificateSchoolName' => ['required', 'string', 'max:255'],
            'certificateIssuerTitle' => ['required', 'string', 'max:255'],
            'certificateIssuerName' => ['required', 'string', 'max:255'],
            'certificateSignatureFile' => ['nullable', 'file', 'image', 'max:4096'],
            'removeCertificateSignature' => ['nullable', 'boolean'],
        ]);
        $maintenanceMode = $request->boolean('maintenanceMode');
        $allowSelfSignup = $request->boolean('allowSelfSignup');
        $removeCertificateSignature = $request->boolean('removeCertificateSignature');

        $settings = AppSetting::query()
            ->pluck('value', 'key')
            ->toArray();
        $currentSignatureUrl = trim((string) ($settings['certificate_signature_url'] ?? ''));
        $signatureUrl = $currentSignatureUrl;

        if ($removeCertificateSignature) {
            $this->deleteManagedSignatureFile($currentSignatureUrl);
            $signatureUrl = '';
        }

        if ($request->hasFile('certificateSignatureFile')) {
            $this->deleteManagedSignatureFile($currentSignatureUrl);
            $path = $request->file('certificateSignatureFile')->store('certificates/signatures', 'public');
            $signatureUrl = Storage::disk('public')->url($path);
        }

        $mapping = [
            'platform_name' => $validated['platformName'],
            'support_email' => $validated['supportEmail'],
            'currency' => 'MZN',
            'maintenance_mode' => $maintenanceMode ? 'true' : 'false',
            'allow_self_signup' => $allowSelfSignup ? 'true' : 'false',
            'default_course_visibility' => $validated['defaultCourseVisibility'],
            'certificate_school_name' => $validated['certificateSchoolName'],
            'certificate_issuer_title' => $validated['certificateIssuerTitle'],
            'certificate_issuer_name' => $validated['certificateIssuerName'],
            'certificate_signature_url' => $signatureUrl,
        ];

        foreach ($mapping as $key => $value) {
            AppSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value],
            );
        }

        return response()->json([
            'message' => 'Definições atualizadas com sucesso.',
            'data' => [
                'platformName' => $validated['platformName'],
                'supportEmail' => $validated['supportEmail'],
                'currency' => 'MZN',
                'maintenanceMode' => $maintenanceMode,
                'allowSelfSignup' => $allowSelfSignup,
                'defaultCourseVisibility' => $validated['defaultCourseVisibility'],
                'certificateSchoolName' => $validated['certificateSchoolName'],
                'certificateIssuerTitle' => $validated['certificateIssuerTitle'],
                'certificateIssuerName' => $validated['certificateIssuerName'],
                'certificateSignatureUrl' => $signatureUrl,
            ],
        ]);
    }

    private function deleteManagedSignatureFile(string $url): void
    {
        $parsedPath = parse_url($url, PHP_URL_PATH);
        if (! is_string($parsedPath) || $parsedPath === '') {
            return;
        }

        $storagePrefix = '/storage/';
        if (! str_starts_with($parsedPath, $storagePrefix)) {
            return;
        }

        $relative = ltrim(substr($parsedPath, strlen($storagePrefix)), '/');
        if ($relative === '' || ! str_starts_with($relative, 'certificates/signatures/')) {
            return;
        }

        Storage::disk('public')->delete($relative);
    }
}
