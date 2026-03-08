<?php

namespace App\Filament\Resources\AudioBookResource\Pages;

use App\Filament\Resources\AudioBookResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAudioBook extends CreateRecord
{
    protected static string $resource = AudioBookResource::class;

    protected function getRedirectUrl(): string{
        return $this->getResource()::getUrl('index');
    }
}
