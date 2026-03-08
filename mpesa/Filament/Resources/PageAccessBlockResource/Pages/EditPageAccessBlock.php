<?php

namespace App\Filament\Resources\PageAccessBlockResource\Pages;

use App\Filament\Resources\PageAccessBlockResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPageAccessBlock extends EditRecord
{
    protected static string $resource = PageAccessBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('regenerateCode')
                ->label('Regenerar codigo')
                ->requiresConfirmation()
                ->action(function (): void {
                    $code = $this->record->regenerateAccessCode();

                    Notification::make()
                        ->title('Codigo regenerado')
                        ->body("Novo codigo: {$code}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
