<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Ebook;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EbookResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\EbookResource\RelationManagers;
use Filament\Forms\Components\Textarea;

class EbookResource extends Resource
{
    protected static ?string $model = Ebook::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Ebooks';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ebook')
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
                            ->unique(Ebook::class, 'slug', ignoreRecord: true),
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
                        Select::make('ebook_serie_id') // Matches the database column
                            ->label('Ebook Serie')
                            ->relationship('ebookSerie', 'name')
                            ->required()
                            ->columnSpanFull(),
                            Textarea::make('description')
                            ->required()
                            ->columnSpanFull(),
                        FileUpload::make('cover_image')
                            ->disk('local')
                            ->directory('protected/ebook-covers')
                            ->visibility('private')
                            ->image()
                            ->columnSpanFull()
                            ->required(),
                        TextInput::make('chapters')
                            ->required()
                            ->numeric()
                            ->columnSpan(1),
                        DatePicker::make('year')
                            ->required()
                            ->columnSpan(1),
                        FileUpload::make(name: 'file')
                            ->disk('local')
                            ->directory('protected/ebooks')
                            ->visibility('private')
                            ->columnSpanFull()
                            ->required(),

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
                Tables\Columns\TextColumn::make('chapters')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('year')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
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
                    ->visible(fn (Ebook $record): bool => $record->status !== 'published')
                    ->action(fn (Ebook $record) => $record->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ])),
                Tables\Actions\Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (Ebook $record): bool => $record->status === 'published')
                    ->action(fn (Ebook $record) => $record->update([
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
            'index' => Pages\ListEbooks::route('/'),
            'create' => Pages\CreateEbook::route('/create'),
            'edit' => Pages\EditEbook::route('/{record}/edit'),
        ];
    }
}
