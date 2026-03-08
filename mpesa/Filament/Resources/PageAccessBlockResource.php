<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageAccessBlockResource\Pages;
use App\Models\PageAccessBlock;
use App\Support\BlockableFrontendPathOptions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageAccessBlockResource extends Resource
{
    protected static ?string $model = PageAccessBlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Bloqueio de paginas';

    protected static ?string $modelLabel = 'Bloqueio de pagina';

    protected static ?string $pluralModelLabel = 'Bloqueios de paginas';

    protected static ?string $navigationGroup = 'Configuracoes';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configurar bloqueio')
                    ->description('Defina o conteudo e a pagina que ficara protegida por codigo.')
                    ->schema([
                        TextInput::make('headline')
                            ->label('Headline')
                            ->required()
                            ->maxLength(160),
                        Textarea::make('complementary_text')
                            ->label('Texto complementar')
                            ->required()
                            ->rows(5)
                            ->maxLength(5000),
                        Select::make('blocked_path')
                            ->label('Escolher pagina para bloquear')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->unique(PageAccessBlock::class, 'blocked_path', ignoreRecord: true)
                            ->options(fn (): array => BlockableFrontendPathOptions::options()),
                        TextInput::make('access_code')
                            ->label('Codigo de pessoal autorizado')
                            ->helperText('Use este codigo para partilhar o acesso autorizado.')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?PageAccessBlock $record): bool => filled($record?->access_code)),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('blocked_path')
                    ->label('Pagina bloqueada')
                    ->searchable(),
                Tables\Columns\TextColumn::make('headline')
                    ->label('Headline')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('access_code')
                    ->label('Codigo')
                    ->copyable()
                    ->copyMessage('Codigo copiado')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPageAccessBlocks::route('/'),
            'create' => Pages\CreatePageAccessBlock::route('/create'),
            'edit' => Pages\EditPageAccessBlock::route('/{record}/edit'),
        ];
    }
}
