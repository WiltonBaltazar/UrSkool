<?php

namespace App\Filament\Resources\EbookSerieResource\Pages;

use App\Filament\Resources\EbookSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEbookSeries extends ListRecords
{
    protected static string $resource = EbookSerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
