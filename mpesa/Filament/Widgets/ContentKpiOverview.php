<?php

namespace App\Filament\Widgets;

use App\Models\Audiobook;
use App\Models\Ebook;
use App\Models\Episode;
use App\Models\Podcast;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentKpiOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalEbooks = Ebook::query()->count();
        $publishedEbooks = Ebook::query()->published()->count();

        $totalAudiobooks = Audiobook::query()->count();
        $publishedAudiobooks = Audiobook::query()->published()->count();

        $totalEpisodes = Episode::query()->count();
        $publishedEpisodes = Episode::query()->published()->count();

        $totalPodcasts = Podcast::query()->count();

        return [
            Stat::make('Ebooks', number_format($totalEbooks))
                ->description(number_format($publishedEbooks) . ' publicados')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('primary'),

            Stat::make('Audiobooks', number_format($totalAudiobooks))
                ->description(number_format($publishedAudiobooks) . ' publicados')
                ->descriptionIcon('heroicon-m-speaker-wave')
                ->color('success'),

            Stat::make('Episodios', number_format($totalEpisodes))
                ->description(number_format($publishedEpisodes) . ' publicados')
                ->descriptionIcon('heroicon-m-microphone')
                ->color('warning'),

            Stat::make('Podcasts', number_format($totalPodcasts))
                ->description('catalogo total')
                ->descriptionIcon('heroicon-m-signal')
                ->color('gray'),
        ];
    }
}
