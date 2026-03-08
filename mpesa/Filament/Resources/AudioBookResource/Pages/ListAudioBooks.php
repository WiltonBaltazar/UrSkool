<?php

namespace App\Filament\Resources\AudioBookResource\Pages;

use App\Filament\Resources\AudioBookResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAudioBooks extends ListRecords
{
    protected static string $resource = AudioBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
