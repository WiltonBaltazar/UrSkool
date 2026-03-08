<?php

namespace App\Console\Commands;

use App\Support\ResponsiveImageVariants;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class GenerateResponsiveImages extends Command
{
    protected $signature = 'media:generate-responsive-images {--force : Regenerate variants even if already up to date}';

    protected $description = 'Generate responsive WebP variants for uploaded media images';

    public function handle(ResponsiveImageVariants $variants): int
    {
        $targets = config('media.responsive.targets', []);

        if (! is_array($targets) || $targets === []) {
            $this->warn('No responsive image targets configured.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $seen = [];
        $recordsScanned = 0;
        $pathsProcessed = 0;
        $variantsGenerated = 0;

        foreach ($targets as $modelClass => $attributes) {
            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }

            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $imageAttributes = array_values(array_filter(
                is_array($attributes) ? $attributes : [],
                static fn (mixed $value): bool => is_string($value) && $value !== ''
            ));

            if ($imageAttributes === []) {
                continue;
            }

            $this->line(sprintf('Scanning %s...', class_basename($modelClass)));

            $columns = array_values(array_unique(array_merge(['id'], $imageAttributes)));

            $modelClass::query()
                ->select($columns)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use (
                    $imageAttributes,
                    $variants,
                    $force,
                    &$seen,
                    &$recordsScanned,
                    &$pathsProcessed,
                    &$variantsGenerated
                ): void {
                    foreach ($rows as $row) {
                        $recordsScanned++;

                        foreach ($imageAttributes as $attribute) {
                            $path = $row->getAttribute($attribute);
                            if (! is_string($path) || trim($path) === '') {
                                continue;
                            }

                            if (isset($seen[$path])) {
                                continue;
                            }

                            $seen[$path] = true;
                            $pathsProcessed++;
                            $variantsGenerated += $variants->generateForPath($path, $force);
                        }
                    }
                });
        }

        $this->info(sprintf(
            'Done. scanned_records=%d unique_paths=%d generated_variants=%d',
            $recordsScanned,
            $pathsProcessed,
            $variantsGenerated
        ));

        return self::SUCCESS;
    }
}
