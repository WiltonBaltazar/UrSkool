<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'platform_name' => 'UrSkool',
            'support_email' => 'support@urskool.test',
            'currency' => 'MZN',
            'maintenance_mode' => 'false',
            'allow_self_signup' => 'true',
            'default_course_visibility' => 'public',
        ];

        foreach ($defaults as $key => $value) {
            AppSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
