<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BusinessKpiOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfWeek = $now->copy()->subDays(6)->startOfDay();

        $totalUsers = User::query()->count();
        $newUsersThisMonth = User::query()
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->count();
        $verifiedUsers = User::query()
            ->whereNotNull('email_verified_at')
            ->count();

        $activeSubscriptions = Subscription::query()->active()->count();
        $pendingSubscriptions = Subscription::query()->pending()->count();
        $paidRevenueThisMonth = (float) Subscription::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->sum('amount_paid');
        $paidRevenueLast7Days = (float) Subscription::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startOfWeek, $now])
            ->sum('amount_paid');

        return [
            Stat::make('Utilizadores', number_format($totalUsers))
                ->description(number_format($newUsersThisMonth) . ' novos este mes')
                ->descriptionIcon('heroicon-m-user-plus')
                ->chart($this->countByDay(User::query(), 'created_at'))
                ->color('primary'),

            Stat::make('Emails verificados', number_format($verifiedUsers))
                ->description($this->percentOf($verifiedUsers, $totalUsers) . '% da base total')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Assinaturas ativas', number_format($activeSubscriptions))
                ->description(number_format($pendingSubscriptions) . ' pendentes')
                ->descriptionIcon('heroicon-m-credit-card')
                ->chart($this->countByDay(Subscription::query()->active(), 'created_at'))
                ->color('warning'),

            Stat::make('Receita paga (mes)', $this->money($paidRevenueThisMonth))
                ->description($this->money($paidRevenueLast7Days) . ' nos ultimos 7 dias')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }

    /**
     * Build a 7-day count trend for sparkline cards.
     */
    private function countByDay(Builder $query, string $column): array
    {
        $days = collect(range(6, 0))
            ->map(fn (int $offset): Carbon => now()->copy()->subDays($offset)->startOfDay());

        return $days
            ->map(function (Carbon $day) use ($query, $column): int {
                return (clone $query)
                    ->whereBetween($column, [$day, $day->copy()->endOfDay()])
                    ->count();
            })
            ->values()
            ->all();
    }

    private function money(float $amount): string
    {
        return 'MZN ' . number_format($amount, 2, '.', ',');
    }

    private function percentOf(int $part, int $whole): string
    {
        if ($whole <= 0) {
            return '0.0';
        }

        return number_format(($part / $whole) * 100, 1, '.', '');
    }
}
