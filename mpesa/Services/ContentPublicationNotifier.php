<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Audiobook;
use App\Models\Ebook;
use App\Models\Episode;
use App\Models\Newsletter;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContentPublicationNotifier
{
    public function __construct(
        private readonly WebPushService $webPushService
    ) {
    }

    public function notifyEpisodePublished(Episode $episode): void
    {
        $episode->loadMissing('podcast');

        $podcastName = $episode->podcast?->name ?? 'Podcast';
        $title = 'Novo episódio disponível';
        $body = sprintf('%s: o episódio "%s" acabou de sair.', $podcastName, $episode->title);
        $url = '/app/episodes/'.$episode->slug;

        $this->createAndBroadcast(
            type: 'episode_published',
            uniqueKey: sprintf('episode_published:%d', $episode->id),
            title: $title,
            body: $body,
            url: $url,
            metadata: [
                'episode_id' => $episode->id,
                'episode_slug' => $episode->slug,
                'podcast_id' => $episode->podcast_id,
                'podcast_slug' => $episode->podcast?->slug,
                'podcast_name' => $podcastName,
            ],
            pushTag: sprintf('episode-published-%d', $episode->id),
            pushData: [
                'kind' => 'episode_published',
                'episode_slug' => $episode->slug,
                'podcast_slug' => $episode->podcast?->slug,
            ],
        );
    }

    public function notifyEbookPublished(Ebook $ebook): void
    {
        $ebook->loadMissing('ebookSerie');

        $seriesName = $ebook->ebookSerie?->name;
        $title = 'Novo e-book disponível';
        $body = sprintf('Novo e-book "%s" já está disponível para leitura.', $ebook->title);
        $url = '/app/ebooks/'.$ebook->slug;

        $this->createAndBroadcast(
            type: 'ebook_published',
            uniqueKey: sprintf('ebook_published:%d', $ebook->id),
            title: $title,
            body: $body,
            url: $url,
            metadata: [
                'ebook_id' => $ebook->id,
                'ebook_slug' => $ebook->slug,
                'ebook_series_id' => $ebook->ebook_serie_id,
                'ebook_series_slug' => $ebook->ebookSerie?->slug,
                'ebook_series_name' => $seriesName,
            ],
            pushTag: sprintf('ebook-published-%d', $ebook->id),
            pushData: [
                'kind' => 'ebook_published',
                'ebook_slug' => $ebook->slug,
                'ebook_series_slug' => $ebook->ebookSerie?->slug,
            ],
        );
    }

    public function notifyAudiobookPublished(Audiobook $audiobook): void
    {
        $audiobook->loadMissing('audiobookSerie');

        $seriesName = $audiobook->audiobookSerie?->name;
        $title = 'Novo audiobook disponível';
        $body = sprintf('Novo audiobook "%s" já está disponível para ouvir.', $audiobook->title);
        $url = '/app/audiobooks/'.$audiobook->slug;

        $this->createAndBroadcast(
            type: 'audiobook_published',
            uniqueKey: sprintf('audiobook_published:%d', $audiobook->id),
            title: $title,
            body: $body,
            url: $url,
            metadata: [
                'audiobook_id' => $audiobook->id,
                'audiobook_slug' => $audiobook->slug,
                'audiobook_series_id' => $audiobook->audiobook_serie_id,
                'audiobook_series_slug' => $audiobook->audiobookSerie?->slug,
                'audiobook_series_name' => $seriesName,
            ],
            pushTag: sprintf('audiobook-published-%d', $audiobook->id),
            pushData: [
                'kind' => 'audiobook_published',
                'audiobook_slug' => $audiobook->slug,
                'audiobook_series_slug' => $audiobook->audiobookSerie?->slug,
            ],
        );
    }

    public function notifyNewsletterPublished(Newsletter $newsletter): void
    {
        $title = 'Novo post publicado';
        $body = sprintf('Novo post "%s" em Papos de Plebeus.', $newsletter->title);
        $url = '/app/newsletters/'.$newsletter->slug;

        $this->createAndBroadcast(
            type: 'newsletter_published',
            uniqueKey: sprintf('newsletter_published:%d', $newsletter->id),
            title: $title,
            body: $body,
            url: $url,
            metadata: [
                'newsletter_id' => $newsletter->id,
                'newsletter_slug' => $newsletter->slug,
                'author' => $newsletter->author,
            ],
            pushTag: sprintf('newsletter-published-%d', $newsletter->id),
            pushData: [
                'kind' => 'newsletter_published',
                'newsletter_slug' => $newsletter->slug,
            ],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $pushData
     */
    private function createAndBroadcast(
        string $type,
        string $uniqueKey,
        string $title,
        string $body,
        string $url,
        array $metadata,
        string $pushTag,
        array $pushData
    ): void {
        $notification = AppNotification::query()->firstOrCreate(
            ['unique_key' => $uniqueKey],
            [
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'metadata' => $metadata,
                'published_at' => now(),
            ]
        );

        if (! $notification->wasRecentlyCreated) {
            return;
        }

        if (! $this->webPushService->isConfigured()) {
            return;
        }

        try {
            $this->webPushService->sendToSubscriptions(
                PushSubscription::query()->get(),
                [
                    'title' => $title,
                    'body' => $body,
                    'icon' => '/logo192.png',
                    'badge' => '/logo192.png',
                    'url' => $url,
                    'tag' => $pushTag,
                    'data' => [
                        'notification_id' => $notification->id,
                        ...$pushData,
                    ],
                ]
            );
        } catch (Throwable $exception) {
            Log::warning('Failed to send push notification for published content.', [
                'type' => $type,
                'unique_key' => $uniqueKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
