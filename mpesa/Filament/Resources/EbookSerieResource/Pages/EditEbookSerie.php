<?php

namespace App\Filament\Resources\EbookSerieResource\Pages;

use App\Filament\Resources\EbookSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEbookSerie extends EditRecord
{
    protected static string $resource = EbookSerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
