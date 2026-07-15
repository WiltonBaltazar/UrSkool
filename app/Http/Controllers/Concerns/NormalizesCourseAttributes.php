<?php

namespace App\Http\Controllers\Concerns;

trait NormalizesCourseAttributes
{
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
