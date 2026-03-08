<?php

namespace App\Filament\Resources\PageAccessBlockResource\Pages;

use App\Filament\Resources\PageAccessBlockResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePageAccessBlock extends CreateRecord
{
    protected static string $resource = PageAccessBlockResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Bloqueio criado')
            ->body("Codigo gerado: {$this->record->access_code}")
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
