<?php

namespace App\Filament\Resources\EbookSerieResource\Pages;

use App\Filament\Resources\EbookSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEbookSerie extends CreateRecord
{
    protected static string $resource = EbookSerieResource::class;

    protected function getRedirectUrl(): string{
        return $this->getResource()::getUrl('index');
    }
}
