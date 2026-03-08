<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Support\AudioDuration;
use Illuminate\Console\Command;

class RecalculateEpisodeDurations extends Command
{
    protected $signature = 'episodes:recalculate-duration
        {--dry-run : Show what would change without persisting}
        {--only-missing : Recalculate only episodes with empty or 00:00:00 duration}';

    protected $description = 'Recalculate episode duration from audio files using ffprobe.';

    public function handle(): int
    {
        $query = Episode::query()->orderBy('id');

        if ($this->option('only-missing')) {
            $query->where(function ($q): void {
                $q->whereNull('duration')
                    ->orWhere('duration', '')
                    ->orWhere('duration', '00:00:00');
            });
        }

        $episodes = $query->get();

        if ($episodes->isEmpty()) {
            $this->info('No episodes found for recalculation.');
            return self::SUCCESS;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($episodes as $episode) {
            $newDuration = AudioDuration::fromPublicDiskPath($episode->audio_file);

            if ($newDuration === null) {
                $failed++;
                $this->warn("Episode {$episode->id}: could not extract duration from '{$episode->audio_file}'.");
                continue;
            }

            if ($episode->duration === $newDuration) {
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $this->line("Episode {$episode->id}: {$episode->duration} -> {$newDuration}");
                $updated++;
                continue;
            }

            $episode->duration = $newDuration;
            $episode->saveQuietly();
            $updated++;
            $this->info("Episode {$episode->id}: updated to {$newDuration}");
        }

        $this->newLine();
        $this->info("Processed: {$episodes->count()}");
        $this->info("Updated: {$updated}");
        $this->line("Unchanged: {$skipped}");
        $this->line("Failed: {$failed}");
        $this->line($isDryRun ? 'Mode: dry-run (no data persisted)' : 'Mode: write');

        return self::SUCCESS;
    }
}

