<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\Audiobook;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\AudioBookResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\AudioBookResource\RelationManagers;
use Filament\Forms\Components\Textarea;

class AudioBookResource extends Resource
{
    protected static ?string $model = Audiobook::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Audiobooks';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('AudioBook')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->unique(ignoreRecord: true)
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                if ($operation !== 'create') {
                                    return;
                                }

                                $set('slug', Str::slug($state));
                            }),
                        TextInput::make('slug')
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(Audiobook::class, 'slug', ignoreRecord: true),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                            ])
                            ->default('draft')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                                if ($state === 'published') {
                                    $set('published_at', now());
                                    return;
                                }

                                $set('published_at', null);
                            }),
                        DateTimePicker::make('published_at')
                            ->label('Published at')
                            ->seconds(false),
                        Select::make('audiobook_serie_id') // Matches the database column
                            ->label('Audiobook Serie')
                            ->relationship('audiobookSerie', 'name')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->required()
                            ->columnSpanFull(),
                        FileUpload::make('cover_image')
                            ->disk('local')
                            ->image()
                            ->columnSpanFull()
                            ->directory('protected/audiobook-covers')
                            ->visibility('private')
                            ->required(),
                        DatePicker::make('year')
                            ->required(),
                        TextInput::make('narrator')
                            ->required(),
                        Repeater::make('chapters')
                            ->relationship('audiobookChapters')
                            ->label('Chapters')
                            ->schema([
                                TextInput::make('title')
                                    ->required(),
                                FileUpload::make('audio_file')
                                    ->disk('local')
                                    ->directory('protected/audiobook-chapters')
                                    ->visibility('private')
                                    ->required(),
                            ])->columnSpan(2)
                            ->collapsible(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('cover_image'),
                Tables\Columns\TextColumn::make('year')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('narrator')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Audiobook $record): bool => $record->status !== 'published')
                    ->action(fn (Audiobook $record) => $record->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ])),
                Tables\Actions\Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (Audiobook $record): bool => $record->status === 'published')
                    ->action(fn (Audiobook $record) => $record->update([
                        'status' => 'draft',
                        'published_at' => null,
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudioBooks::route('/'),
            'create' => Pages\CreateAudioBook::route('/create'),
            'edit' => Pages\EditAudioBook::route('/{record}/edit'),
        ];
    }
}
