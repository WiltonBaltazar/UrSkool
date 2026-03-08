<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Plan;
use App\Models\Role;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?array $pendingSubscription = null;
    protected bool $shouldGrantAdminRole = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->shouldGrantAdminRole = (bool) ($data['grant_admin_role'] ?? false);

        if (($data['assign_subscription'] ?? false) && ! empty($data['subscription_plan_id'])) {
            $this->pendingSubscription = [
                'plan_id' => (int) $data['subscription_plan_id'],
                'duration_days' => (int) ($data['subscription_duration_days'] ?? 0),
                'start_date' => $data['subscription_start_date'] ?? now(),
            ];
        }

        unset(
            $data['grant_admin_role'],
            $data['assign_subscription'],
            $data['subscription_plan_id'],
            $data['subscription_duration_days'],
            $data['subscription_start_date'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->shouldGrantAdminRole) {
            $adminRole = Role::withoutGlobalScopes()->firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
            ]);

            $this->record->assignRole($adminRole);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (! $this->pendingSubscription) {
            return;
        }

        $plan = Plan::query()->find($this->pendingSubscription['plan_id']);
        $durationDays = $this->pendingSubscription['duration_days'];

        if (! $plan || $durationDays < 1) {
            Notification::make()
                ->title('User created, but subscription was not assigned')
                ->body('Please verify plan and duration.')
                ->warning()
                ->send();
            return;
        }

        $startDate = Carbon::parse($this->pendingSubscription['start_date']);
        $endDate = $startDate->copy()->addDays($durationDays);

        $this->record->subscribeToPlan($plan, [
            'status' => 'active',
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'payment_source' => 'manual',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    protected function getRedirectUrl(): string{
        return $this->getResource()::getUrl('index');
    }
}
