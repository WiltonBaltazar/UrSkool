<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Episode;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\PodcastResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PodcastResource\RelationManagers;
use App\Filament\Resources\EpisodeResource\Pages\EditEpisode;
use App\Filament\Resources\EpisodeResource\Pages\ListEpisodes;
use App\Filament\Resources\EpisodeResource\Pages\CreateEpisode;

class EpisodeResource extends Resource
{
    protected static ?string $model = Episode::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Podcast';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Podcast')
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
                            ->unique(Episode::class, 'slug', ignoreRecord: true),
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
                        Select::make('podcast_id') // Matches the database column
                            ->label('Podcast Name')
                            ->relationship('podcast', 'name')
                            ->required()
                            ->columnSpanFull(),
                        FileUpload::make('cover_image')
                            ->disk('public')
                            ->image()
                            ->columnSpanFull()
                            ->required(),
                        Textarea::make('description')
                            ->required(),
                        TextInput::make('guest')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('audio_file')
                            ->disk('local')
                            ->directory('protected/podcast-episodes')
                            ->visibility('private')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', 'audio/mpga'])
                            ->maxSize(204800)
                            ->required()
                            ->columnSpanFull(),
                        DateTimePicker::make('release_date')
                            ->required(),
                        TextInput::make('duration')
                            ->label('Duration (auto)')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Calculated automatically from the uploaded audio file.')
                            ->maxLength(255),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('guest')
                    ->searchable(),
                Tables\Columns\TextColumn::make('release_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
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
                    ->visible(fn (Episode $record): bool => $record->status !== 'published')
                    ->action(fn (Episode $record) => $record->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ])),
                Tables\Actions\Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (Episode $record): bool => $record->status === 'published')
                    ->action(fn (Episode $record) => $record->update([
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
            'index' => ListEpisodes::route('/'),
            'create' => CreateEpisode::route('/create'),
            'edit' => EditEpisode::route('/{record}/edit'),
        ];
    }
}
