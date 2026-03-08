<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

        $mapping = [
            'platform_name' => $validated['platformName'],
            'support_email' => $validated['supportEmail'],
            'currency' => 'MZN',
            'maintenance_mode' => $validated['maintenanceMode'] ? 'true' : 'false',
            'allow_self_signup' => $validated['allowSelfSignup'] ? 'true' : 'false',
            'default_course_visibility' => $validated['defaultCourseVisibility'],
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
                'maintenanceMode' => $validated['maintenanceMode'],
                'allowSelfSignup' => $validated['allowSelfSignup'],
                'defaultCourseVisibility' => $validated['defaultCourseVisibility'],
            ],
        ]);
    }
}
