<?php

namespace App\Models;

use App\Support\BlockableFrontendPathOptions;
use App\Support\PageAccessPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class PageAccessBlock extends Model
{
    protected $fillable = [
        'headline',
        'complementary_text',
        'blocked_path',
        'access_code',
        'access_code_hash',
        'is_active',
    ];

    protected $hidden = [
        'access_code_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'access_code' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $block): void {
            $legacyResolvedPath = BlockableFrontendPathOptions::pathFromLegacyLabel((string) $block->blocked_path);

            $block->blocked_path = PageAccessPath::normalize(
                $legacyResolvedPath ?? (string) $block->blocked_path
            );
        });

        static::creating(function (self $block): void {
            if (blank($block->access_code) || blank($block->access_code_hash)) {
                $block->assignAccessCode(self::generateAccessCode());
            }
        });
    }

    public function verifyAccessCode(string $providedCode): bool
    {
        $normalizedCode = self::normalizeCode($providedCode);

        if ($normalizedCode === '' || blank($this->access_code_hash)) {
            return false;
        }

        return Hash::check($normalizedCode, $this->access_code_hash);
    }

    public function regenerateAccessCode(): string
    {
        $code = self::generateAccessCode();
        $this->assignAccessCode($code);
        $this->save();

        return $code;
    }

    public function assignAccessCode(string $code): void
    {
        $normalizedCode = self::normalizeCode($code);
        $formattedCode = self::formatCodeForDisplay($normalizedCode);

        $this->access_code = $formattedCode;
        $this->access_code_hash = Hash::make($normalizedCode);
    }

    public static function generateAccessCode(): string
    {
        $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $parts = [];

        for ($partIndex = 0; $partIndex < 3; $partIndex++) {
            $segment = '';

            for ($charIndex = 0; $charIndex < 4; $charIndex++) {
                $position = random_int(0, strlen($charset) - 1);
                $segment .= $charset[$position];
            }

            $parts[] = $segment;
        }

        return implode('-', $parts);
    }

    protected static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    protected static function formatCodeForDisplay(string $normalizedCode): string
    {
        if ($normalizedCode === '') {
            return '';
        }

        return implode('-', str_split($normalizedCode, 4));
    }
}
