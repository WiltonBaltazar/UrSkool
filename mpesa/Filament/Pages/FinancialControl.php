<?php

namespace App\Filament\Pages;

use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialControl extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Finanças';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Controlo Financeiro';

    protected static ?string $slug = 'financial-control';

    protected static string $view = 'filament.pages.financial-control';

    public string $period = '30d';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $status = 'all';

    public string $source = 'all';

    public string $search = '';

    public array $metrics = [];

    public array $revenueBySource = [];

    public array $revenueByPlan = [];

    public array $transactions = [];

    public function mount(): void
    {
        $this->setQuickPeriod('30d');
    }

    public function setQuickPeriod(string $period): void
    {
        if (! in_array($period, ['7d', '30d', '90d', '12m'], true)) {
            return;
        }

        $this->period = $period;
        $now = now();

        $startDate = match ($period) {
            '7d' => $now->copy()->subDays(6),
            '30d' => $now->copy()->subDays(29),
            '90d' => $now->copy()->subDays(89),
            '12m' => $now->copy()->subMonthsNoOverflow(12),
            default => $now->copy()->subDays(29),
        };

        $this->dateFrom = $startDate->toDateString();
        $this->dateTo = $now->toDateString();

        $this->refreshDashboard();
    }

    public function applyFilters(): void
    {
        $this->period = 'custom';
        $this->normalizeDateRange();
        $this->refreshDashboard();
    }

    public function resetFilters(): void
    {
        $this->status = 'all';
        $this->source = 'all';
        $this->search = '';
        $this->setQuickPeriod('30d');
    }

    protected function refreshDashboard(): void
    {
        $baseQuery = $this->buildBaseQuery();
        $this->metrics = $this->buildMetrics(clone $baseQuery);
        $this->revenueBySource = $this->buildRevenueBySource(clone $baseQuery);
        $this->revenueByPlan = $this->buildRevenueByPlan(clone $baseQuery);
        $this->transactions = $this->buildTransactions(clone $baseQuery);
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

    protected function buildBaseQuery(): Builder
    {
        $query = Subscription::query()
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoin('users', 'users.id', '=', 'subscriptions.user_id');

        if ($this->dateFrom) {
            $query->whereDate('subscriptions.created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('subscriptions.created_at', '<=', $this->dateTo);
        }

        if ($this->status !== 'all') {
            $query->where('subscriptions.status', $this->status);
        }

        if ($this->source !== 'all') {
            if ($this->source === 'mpesa') {
                $query->where(function ($builder): void {
                    $builder
                        ->where('subscriptions.payment_source', 'mpesa')
                        ->orWhere(function ($inner): void {
                            $inner
                                ->whereNull('subscriptions.payment_source')
                                ->whereNotNull('subscriptions.mpesa_transaction_id');
                        });
                });
            } elseif ($this->source === 'manual') {
                $query->where(function ($builder): void {
                    $builder
                        ->where('subscriptions.payment_source', 'manual')
                        ->orWhere(function ($inner): void {
                            $inner
                                ->whereNull('subscriptions.payment_source')
                                ->whereNull('subscriptions.mpesa_transaction_id');
                        });
                });
            } else {
                $query->where('subscriptions.payment_source', $this->source);
            }
        }

        $term = trim($this->search);

        if ($term !== '') {
            $likeTerm = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';

            $query->where(function ($builder) use ($likeTerm): void {
                $builder
                    ->where('subscriptions.payment_reference', 'like', $likeTerm)
                    ->orWhere('subscriptions.mpesa_transaction_id', 'like', $likeTerm)
                    ->orWhere('users.first_name', 'like', $likeTerm)
                    ->orWhere('users.last_name', 'like', $likeTerm)
                    ->orWhere('users.email', 'like', $likeTerm)
                    ->orWhere('users.phone_number', 'like', $likeTerm);
            });
        }

        return $query;
    }

    protected function buildMetrics(Builder $baseQuery): array
    {
        $amountExpression = $this->amountExpression();
        $paidQuery = (clone $baseQuery)->where('subscriptions.payment_status', 'paid');

        $grossRevenue = (float) (clone $paidQuery)->sum(DB::raw($amountExpression));
        $netRevenue = (float) (clone $paidQuery)
            ->where('subscriptions.status', 'active')
            ->sum(DB::raw($amountExpression));
        $paidTransactions = (int) (clone $paidQuery)->count('subscriptions.id');
        $payingCustomers = (int) (clone $paidQuery)
            ->distinct()
            ->count('subscriptions.user_id');
        $averageTicket = $paidTransactions > 0 ? $grossRevenue / $paidTransactions : 0.0;

        $mrr = (float) (clone $paidQuery)->sum(DB::raw("
            CASE
                WHEN plans.duration_days > 0 THEN ({$amountExpression} / plans.duration_days) * 30
                ELSE 0
            END
        "));
        $arr = $mrr * 12;

        $totalTransactions = (int) (clone $baseQuery)->count('subscriptions.id');
        $paymentSuccessRate = $totalTransactions > 0
            ? ($paidTransactions / $totalTransactions) * 100
            : 0.0;

        return [
            [
                'label' => 'Receita Bruta',
                'value' => $this->formatMoney($grossRevenue),
                'meta' => 'Pagamentos confirmados',
            ],
            [
                'label' => 'Subscrições Pagas',
                'value' => number_format($paidTransactions, 0, ',', '.'),
                'meta' => 'Transações pagas',
            ],
            [
                'label' => 'Clientes Pagantes',
                'value' => number_format($payingCustomers, 0, ',', '.'),
                'meta' => 'Utilizadores únicos',
            ],
            [
                'label' => 'Receita Líquida',
                'value' => $this->formatMoney($netRevenue),
                'meta' => 'Subscrições ativas',
            ],
            [
                'label' => 'Ticket Médio',
                'value' => $this->formatMoney($averageTicket),
                'meta' => 'Receita por transação',
            ],
            [
                'label' => 'MRR Estimado',
                'value' => $this->formatMoney($mrr),
                'meta' => 'Receita mensal recorrente',
            ],
            [
                'label' => 'ARR Estimado',
                'value' => $this->formatMoney($arr),
                'meta' => 'Receita anual recorrente',
            ],
            [
                'label' => 'Taxa de Sucesso',
                'value' => number_format($paymentSuccessRate, 1, ',', '.') . '%',
                'meta' => 'Pagas vs totais',
            ],
        ];
    }

    protected function buildRevenueBySource(Builder $baseQuery): array
    {
        $amountExpression = $this->amountExpression();

        $rows = (clone $baseQuery)
            ->where('subscriptions.payment_status', 'paid')
            ->selectRaw("
                CASE
                    WHEN COALESCE(subscriptions.payment_source, '') = 'manual' THEN 'Manual'
                    WHEN COALESCE(subscriptions.payment_source, '') = 'mpesa' OR subscriptions.mpesa_transaction_id IS NOT NULL THEN 'M-Pesa'
                    ELSE 'Outro'
                END as source_label
            ")
            ->selectRaw('COUNT(subscriptions.id) as transactions')
            ->selectRaw("SUM({$amountExpression}) as revenue")
            ->groupBy('source_label')
            ->orderByDesc('revenue')
            ->get();

        return $rows
            ->map(function ($row): array {
                return [
                    'source' => $row->source_label,
                    'transactions' => (int) $row->transactions,
                    'revenue' => $this->formatMoney((float) $row->revenue),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildRevenueByPlan(Builder $baseQuery): array
    {
        $amountExpression = $this->amountExpression();

        $rows = (clone $baseQuery)
            ->where('subscriptions.payment_status', 'paid')
            ->selectRaw("COALESCE(plans.name, 'Sem plano') as plan_name")
            ->selectRaw('COUNT(subscriptions.id) as transactions')
            ->selectRaw('COUNT(DISTINCT subscriptions.user_id) as customers')
            ->selectRaw("SUM({$amountExpression}) as revenue")
            ->groupBy('plan_name')
            ->orderByDesc('revenue')
            ->get();

        return $rows
            ->map(function ($row): array {
                return [
                    'plan' => $row->plan_name,
                    'transactions' => (int) $row->transactions,
                    'customers' => (int) $row->customers,
                    'revenue' => $this->formatMoney((float) $row->revenue),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildTransactions(Builder $baseQuery): array
    {
        $amountExpression = $this->amountExpression();

        $rows = (clone $baseQuery)
            ->select('subscriptions.id')
            ->selectRaw('subscriptions.created_at')
            ->selectRaw('subscriptions.status')
            ->selectRaw('subscriptions.payment_status')
            ->selectRaw('subscriptions.payment_reference')
            ->selectRaw('subscriptions.mpesa_transaction_id')
            ->selectRaw("COALESCE(subscriptions.currency, 'MZN') as currency")
            ->selectRaw("COALESCE(plans.name, 'Sem plano') as plan_name")
            ->selectRaw("TRIM(CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, ''))) as user_name")
            ->selectRaw('users.phone_number as user_phone')
            ->selectRaw('users.email as user_email')
            ->selectRaw("
                CASE
                    WHEN COALESCE(subscriptions.payment_source, '') = 'manual' THEN 'Manual'
                    WHEN COALESCE(subscriptions.payment_source, '') = 'mpesa' OR subscriptions.mpesa_transaction_id IS NOT NULL THEN 'M-Pesa'
                    ELSE 'Outro'
                END as source_label
            ")
            ->selectRaw("{$amountExpression} as amount_paid")
            ->orderByDesc('subscriptions.created_at')
            ->limit(50)
            ->get();

        return $rows
            ->map(function ($row): array {
                $userName = trim((string) $row->user_name);

                return [
                    'date' => Carbon::parse($row->created_at)->format('d/m/Y H:i'),
                    'user' => $userName !== '' ? $userName : 'Sem nome',
                    'contact' => $row->user_phone ?: $row->user_email ?: '-',
                    'plan' => $row->plan_name,
                    'source' => $row->source_label,
                    'status' => Str::title((string) $row->status),
                    'payment_status' => Str::title((string) $row->payment_status),
                    'amount' => $this->formatMoney((float) $row->amount_paid),
                    'reference' => $row->payment_reference ?: ($row->mpesa_transaction_id ?: '-'),
                ];
            })
            ->values()
            ->all();
    }

    protected function amountExpression(): string
    {
        return "
            CASE
                WHEN subscriptions.amount_paid IS NOT NULL THEN subscriptions.amount_paid
                WHEN COALESCE(subscriptions.payment_source, '') = 'manual' AND subscriptions.mpesa_transaction_id IS NULL THEN 0
                ELSE COALESCE(plans.promo_price, plans.price, 0)
            END
        ";
    }

    protected function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' MT';
    }
}
