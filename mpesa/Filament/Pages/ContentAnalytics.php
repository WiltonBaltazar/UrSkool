<?php

namespace App\Filament\Pages;

use App\Models\Audiobook;
use App\Models\ContentConsumptionEvent;
use App\Models\Ebook;
use App\Models\Episode;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContentAnalytics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Consumo de Conteúdo';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Analytics de Conteúdo';

    protected static ?string $slug = 'content-analytics';

    protected static string $view = 'filament.pages.content-analytics';

    public string $period = '30d';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $contentType = 'all';

    public array $metrics = [];

    public array $topViewed = [];

    public array $topPlayed = [];

    public function mount(): void
    {
        $this->setQuickPeriod('30d');
    }

    public function setQuickPeriod(string $period): void
    {
        if (! in_array($period, ['7d', '30d', '90d'], true)) {
            return;
        }

        $this->period = $period;
        $now = now();

        $startDate = match ($period) {
            '7d' => $now->copy()->subDays(6),
            '30d' => $now->copy()->subDays(29),
            '90d' => $now->copy()->subDays(89),
            default => $now->copy()->subDays(29),
        };

        $this->dateFrom = $startDate->toDateString();
        $this->dateTo = $now->toDateString();

        $this->refreshAnalytics();
    }

    public function applyFilters(): void
    {
        $this->period = 'custom';
        $this->normalizeDateRange();
        $this->refreshAnalytics();
    }

    public function resetFilters(): void
    {
        $this->contentType = 'all';
        $this->setQuickPeriod('30d');
    }

    public function contentTypeOptions(): array
    {
        return [
            'all' => 'Todos',
            'ebook' => 'E-books',
            'audiobook' => 'Audiobooks',
            'episode' => 'Episódios',
        ];
    }

    protected function normalizeDateRange(): void
    {
        if (! $this->dateFrom || ! $this->dateTo) {
            return;
        }

        $start = Carbon::parse($this->dateFrom);
        $end = Carbon::parse($this->dateTo);

        if ($start->greaterThan($end)) {
            $this->dateFrom = $end->toDateString();
            $this->dateTo = $start->toDateString();
        }
    }

    protected function refreshAnalytics(): void
    {
        $baseQuery = $this->buildBaseQuery();
        $this->metrics = $this->buildMetrics(clone $baseQuery);
        $this->topViewed = $this->buildTopContent(clone $baseQuery, ContentConsumptionEvent::EVENT_VIEW);
        $this->topPlayed = $this->buildTopContent(clone $baseQuery, ContentConsumptionEvent::EVENT_PLAY);
    }

    protected function buildBaseQuery(): Builder
    {
        $query = ContentConsumptionEvent::query();

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $modelMap = $this->contentTypeModelMap();
        if ($this->contentType !== 'all' && isset($modelMap[$this->contentType])) {
            $query->where('content_type', $modelMap[$this->contentType]);
        }

        return $query;
    }

    protected function buildMetrics(Builder $baseQuery): array
    {
        $totalViews = (int) (clone $baseQuery)
            ->where('event_type', ContentConsumptionEvent::EVENT_VIEW)
            ->count();

        $totalPlays = (int) (clone $baseQuery)
            ->where('event_type', ContentConsumptionEvent::EVENT_PLAY)
            ->count();

        $activeUsers = (int) (clone $baseQuery)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        $uniqueContent = (int) (clone $baseQuery)
            ->select(['content_type', 'content_id'])
            ->distinct()
            ->get()
            ->count();

        $totalEvents = (int) (clone $baseQuery)->count();

        return [
            [
                'label' => 'Visualizações',
                'value' => number_format($totalViews, 0, ',', '.'),
                'meta' => 'Eventos de view no período',
            ],
            [
                'label' => 'Reproduções',
                'value' => number_format($totalPlays, 0, ',', '.'),
                'meta' => 'Eventos de play no período',
            ],
            [
                'label' => 'Utilizadores Ativos',
                'value' => number_format($activeUsers, 0, ',', '.'),
                'meta' => 'Utilizadores únicos',
            ],
            [
                'label' => 'Conteúdos Consumidos',
                'value' => number_format($uniqueContent, 0, ',', '.'),
                'meta' => 'Conteúdos únicos',
            ],
            [
                'label' => 'Eventos Totais',
                'value' => number_format($totalEvents, 0, ',', '.'),
                'meta' => 'Views + plays',
            ],
        ];
    }

    protected function buildTopContent(Builder $baseQuery, string $eventType): array
    {
        $rows = (clone $baseQuery)
            ->where('event_type', $eventType)
            ->selectRaw('content_type, content_id, COUNT(*) as total_events')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
            ->selectRaw('MAX(created_at) as last_event_at')
            ->groupBy('content_type', 'content_id')
            ->orderByDesc('total_events')
            ->limit(15)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $modelLabels = $this->modelLabelMap();
        $titles = $this->resolveContentTitles($rows);

        return $rows
            ->values()
            ->map(function ($row, int $index) use ($modelLabels, $titles): array {
                $key = "{$row->content_type}:{$row->content_id}";
                $fallbackTitle = "Conteúdo #{$row->content_id}";

                return [
                    'position' => $index + 1,
                    'title' => $titles[$key] ?? $fallbackTitle,
                    'type' => $modelLabels[$row->content_type] ?? class_basename((string) $row->content_type),
                    'events' => (int) $row->total_events,
                    'unique_users' => (int) $row->unique_users,
                    'last_event_at' => $this->formatDateTime($row->last_event_at),
                ];
            })
            ->all();
    }

    protected function resolveContentTitles(Collection $rows): array
    {
        $idsByModel = [];

        foreach ($rows as $row) {
            $modelClass = (string) $row->content_type;
            $idsByModel[$modelClass][] = (int) $row->content_id;
        }

        $titles = [];

        foreach ($idsByModel as $modelClass => $ids) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $uniqueIds = array_values(array_unique($ids));
            $records = $modelClass::query()
                ->whereIn('id', $uniqueIds)
                ->get(['id', 'title']);

            foreach ($records as $record) {
                $titles["{$modelClass}:{$record->id}"] = (string) $record->title;
            }
        }

        return $titles;
    }

    protected function formatDateTime(mixed $value): string
    {
        if (! $value) {
            return 'N/A';
        }

        return Carbon::parse($value)->format('d/m/Y H:i');
    }

    /**
     * @return array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected function contentTypeModelMap(): array
    {
        return [
            'ebook' => Ebook::class,
            'audiobook' => Audiobook::class,
            'episode' => Episode::class,
        ];
    }

    /**
     * @return array<class-string<\Illuminate\Database\Eloquent\Model>, string>
     */
    protected function modelLabelMap(): array
    {
        return [
            Ebook::class => 'E-book',
            Audiobook::class => 'Audiobook',
            Episode::class => 'Episódio',
        ];
    }
}

