<?php

namespace App\Filament\Resources\AudiobookSerieResource\Pages;

use App\Filament\Resources\AudiobookSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAudiobookSeries extends ListRecords
{
    protected static string $resource = AudiobookSerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
