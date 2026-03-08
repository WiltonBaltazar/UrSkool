<?php

namespace App\Support;

use App\Models\Audiobook;
use App\Models\AudiobookSerie;
use App\Models\Ebook;
use App\Models\EbookSerie;
use App\Models\Episode;
use App\Models\Newsletter;
use App\Models\Podcast;
use Illuminate\Support\Facades\Schema;

class BlockableFrontendPathOptions
{
    public static function options(): array
    {
        $labelsToPaths = self::buildLabelToPathMap();
        $options = [];

        foreach ($labelsToPaths as $label => $path) {
            $options[$path] = $label;
        }

        return $options;
    }

    public static function labelForPath(string $path): ?string
    {
        $normalizedPath = PageAccessPath::normalize($path);
        $label = array_search($normalizedPath, self::buildLabelToPathMap(), true);

        return is_string($label) ? $label : null;
    }

    public static function pathFromLegacyLabel(string $value): ?string
    {
        $map = self::buildLabelToPathMap();
        $rawValue = trim($value);

        if ($rawValue === '') {
            return null;
        }

        if (array_key_exists($rawValue, $map)) {
            return $map[$rawValue];
        }

        $withoutLeadingSlash = ltrim($rawValue, '/');

        if (array_key_exists($withoutLeadingSlash, $map)) {
            return $map[$withoutLeadingSlash];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected static function buildLabelToPathMap(): array
    {
        $labelToPath = [
            'Publico: Inicio' => '/',
            'Publico: Login' => '/login',
            'Publico: Criar conta' => '/signup',
            'Publico: Recuperar palavra-passe' => '/forgot-password',
            'Publico: Redefinir palavra-passe' => '/reset-password',
            'Publico: Termos e condicoes' => '/termos-e-condicoes',
            'Publico: Politicas de privacidade' => '/politicas-de-privacidade',
            'App: Inicio' => '/app',
            'App: Podcasts' => '/app/podcasts',
            'App: Episodios' => '/app/episodes',
            'App: Newsletters' => '/app/newsletters',
            'App: Audiobooks' => '/app/audiobooks',
            'App: Ebooks' => '/app/ebooks',
            'App: Series de Ebooks' => '/app/ebooks/series',
            'App: Lendinhas' => '/app/lendinhas',
            'App: Perfil' => '/app/profile',
            'App: Notificacoes' => '/app/notifications',
        ];

        self::appendPodcastOptions($labelToPath);
        self::appendEpisodeOptions($labelToPath);
        self::appendNewsletterOptions($labelToPath);
        self::appendAudiobookOptions($labelToPath);
        self::appendEbookOptions($labelToPath);
        self::appendSeriesOptions($labelToPath);

        return $labelToPath;
    }

    /**
     * @param array<string, string> $labelToPath
     */
    protected static function appendPodcastOptions(array &$labelToPath): void
    {
        if (! Schema::hasTable('podcasts')) {
            return;
        }

        Podcast::query()
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->each(function (Podcast $podcast) use (&$labelToPath): void {
                self::pushOption($labelToPath, "Podcast: {$podcast->name}", "/app/podcasts/{$podcast->slug}");
            });
    }

    /**
     * @param array<string, string> $labelToPath
     */
    protected static function appendEpisodeOptions(array &$labelToPath): void
    {
        if (! Schema::hasTable('episodes')) {
            return;
        }

        Episode::query()
            ->orderBy('title')
            ->get(['title', 'slug'])
            ->each(function (Episode $episode) use (&$labelToPath): void {
                self::pushOption($labelToPath, "Episodio: {$episode->title}", "/app/episodes/{$episode->slug}");
            });
    }

    /**
     * @param array<string, string> $labelToPath
     */
    protected static function appendNewsletterOptions(array &$labelToPath): void
    {
        if (! Schema::hasTable('newsletters')) {
            return;
        }

        Newsletter::query()
            ->orderBy('title')
            ->get(['title', 'slug'])
            ->each(function (Newsletter $newsletter) use (&$labelToPath): void {
                self::pushOption($labelToPath, "Newsletter: {$newsletter->title}", "/app/newsletters/{$newsletter->slug}");
            });
    }

    /**
     * @param array<string, string> $labelToPath
     */
    protected static function appendAudiobookOptions(array &$labelToPath): void
    {
        if (! Schema::hasTable('audiobooks')) {
            return;
        }

        Audiobook::query()
            ->orderBy('title')
            ->get(['title', 'slug'])
            ->each(function (Audiobook $audiobook) use (&$labelToPath): void {
                self::pushOption($labelToPath, "Audiobook: {$audiobook->title}", "/app/audiobooks/{$audiobook->slug}");
            });
    }

    /**
     * @param array<string, string> $labelToPath
     */
    protected static function appendEbookOptions(array &$labelToPath): void
    {
        if (! Schema::hasTable('ebooks')) {
            return;
        }

        Ebook::query()
            ->orderBy('title')
            ->get(['title', 'slug'])
            ->each(function (Ebook $ebook) use (&$labelToPath): void {
                self::pushOption($labelToPath, "Ebook: {$ebook->title}", "/app/ebooks/{$ebook->slug}");
                self::pushOption($labelToPath, "Ebook (Leitura): {$ebook->title}", "/app/ebooks/{$ebook->slug}/read");
            });
    }

    /**
     * @param array<string, string> $labelToPath
     */
    protected static function appendSeriesOptions(array &$labelToPath): void
    {
        if (Schema::hasTable('ebook_series')) {
            EbookSerie::query()
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->each(function (EbookSerie $series) use (&$labelToPath): void {
                    self::pushOption($labelToPath, "Serie Ebook: {$series->name}", "/app/ebooks/series/{$series->slug}");
                });
        }

        if (Schema::hasTable('audiobook_series')) {
            AudiobookSerie::query()
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->each(function (AudiobookSerie $series) use (&$labelToPath): void {
                    self::pushOption($labelToPath, "Serie Audiobook: {$series->name}", "/app/audiobooks/series/{$series->slug}");
                });
        }
    }

    /**
     * @param array<string, string> $labelToPath
     */
    protected static function pushOption(array &$labelToPath, string $label, string $path): void
    {
        $normalizedPath = PageAccessPath::normalize($path);

        if (in_array($normalizedPath, $labelToPath, true)) {
            return;
        }

        $finalLabel = $label;
        $counter = 2;

        while (array_key_exists($finalLabel, $labelToPath)) {
            $finalLabel = "{$label} ({$counter})";
            $counter++;
        }

        $labelToPath[$finalLabel] = $normalizedPath;
    }
}
