<?php

namespace App\Filament\Resources\AudiobookSerieResource\Pages;

use App\Filament\Resources\AudiobookSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAudiobookSerie extends CreateRecord
{
    protected static string $resource = AudiobookSerieResource::class;

    protected function getRedirectUrl(): string{
        return $this->getResource()::getUrl('index');
    }
}
