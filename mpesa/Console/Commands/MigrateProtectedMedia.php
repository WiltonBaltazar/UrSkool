<?php

namespace App\Console\Commands;

use App\Models\AudiobookChapter;
use App\Models\Ebook;
use App\Services\ProtectedMediaService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class MigrateProtectedMedia extends Command
{
    protected $signature = 'media:migrate-protected';

    protected $description = 'Move premium media files from public storage to private storage';

    public function handle(ProtectedMediaService $protectedMediaService): int
    {
        $stats = [
            'checked' => 0,
            'migrated' => 0,
            'unchanged' => 0,
        ];

        $this->line('Migrating Ebook::file...');
        $this->migrateModelAttribute(
            modelClass: Ebook::class,
            attribute: 'file',
            targetDirectory: ProtectedMediaService::EBOOK_DIRECTORY,
            protectedMediaService: $protectedMediaService,
            stats: $stats
        );

        $this->line('Migrating AudiobookChapter::audio_file...');
        $this->migrateModelAttribute(
            modelClass: AudiobookChapter::class,
            attribute: 'audio_file',
            targetDirectory: ProtectedMediaService::AUDIOBOOK_CHAPTER_DIRECTORY,
            protectedMediaService: $protectedMediaService,
            stats: $stats
        );

        $this->info(sprintf(
            'Done. checked=%d migrated=%d unchanged=%d',
            $stats['checked'],
            $stats['migrated'],
            $stats['unchanged']
        ));

        return self::SUCCESS;
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<string, int> $stats
     */
    private function migrateModelAttribute(
        string $modelClass,
        string $attribute,
        string $targetDirectory,
        ProtectedMediaService $protectedMediaService,
        array &$stats
    ): void {
        $modelClass::query()
            ->whereNotNull($attribute)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (
                $attribute,
                $targetDirectory,
                $protectedMediaService,
                &$stats
            ): void {
                foreach ($rows as $row) {
                    $stats['checked']++;
                    $before = trim((string) $row->getAttribute($attribute));

                    if ($before === '') {
                        $stats['unchanged']++;
                        continue;
                    }

                    $after = $protectedMediaService->ensureStoredOnPrivateDisk(
                        $row,
                        $attribute,
                        $targetDirectory
                    );

                    if (is_string($after) && $after !== '' && $after !== $before) {
                        $stats['migrated']++;
                    } else {
                        $stats['unchanged']++;
                    }
                }
            });
    }
}
