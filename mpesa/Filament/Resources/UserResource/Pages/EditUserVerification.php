<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Resources\UserResource;
use Filament\Forms\Concerns\InteractsWithForms;

class EditUserVerification extends Page implements HasForms
{
    use InteractsWithForms;
    protected static string $resource = UserResource::class;

    

    protected static string $view = 'filament.resources.user-resource.pages.edit-user-verification';
}
