<?php

namespace App\Filament\Resources\PageAccessBlockResource\Pages;

use App\Filament\Resources\PageAccessBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPageAccessBlocks extends ListRecords
{
    protected static string $resource = PageAccessBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Criar bloqueio e gerar codigo'),
        ];
    }
}
