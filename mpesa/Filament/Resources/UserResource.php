<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\PermissionRegistrar;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'User Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('profile_photo')
                    ->disk('public')
                    ->image(),
                Forms\Components\TextInput::make('phone_number')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Section::make('Admin Access')
                    ->description('Promote this new account to admin access.')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        Forms\Components\Toggle::make('grant_admin_role')
                            ->label('Grant admin role')
                            ->default(false)
                            ->helperText('Admin users can access /admin after verifying their email.'),
                    ]),
                Forms\Components\Section::make('Initial Subscription')
                    ->description('Assign a subscription plan when creating the user.')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        Forms\Components\Toggle::make('assign_subscription')
                            ->label('Assign subscription now')
                            ->default(true)
                            ->live(),
                        Forms\Components\Select::make('subscription_plan_id')
                            ->label('Plan')
                            ->options(fn () => Plan::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get): bool => (bool) $get('assign_subscription'))
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('assign_subscription'))
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                if (!$state) {
                                    return;
                                }

                                $plan = Plan::query()->find($state);
                                if ($plan?->duration_days) {
                                    $set('subscription_duration_days', $plan->duration_days);
                                }
                            }),
                        Forms\Components\TextInput::make('subscription_duration_days')
                            ->label('Duration (days)')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn (Forms\Get $get): bool => (bool) $get('assign_subscription'))
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('assign_subscription'))
                            ->default(30),
                        Forms\Components\DateTimePicker::make('subscription_start_date')
                            ->label('Subscription start date')
                            ->default(now())
                            ->required(fn (Forms\Get $get): bool => (bool) $get('assign_subscription'))
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('assign_subscription')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fullName')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
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
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('promote_to_admin')
                    ->label('Promote to admin')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! $record->hasAnyRole(['admin', 'super-admin']))
                    ->action(function (User $record): void {
                        $adminRole = Role::withoutGlobalScopes()->firstOrCreate([
                            'name' => 'admin',
                            'guard_name' => 'web',
                        ]);

                        $record->assignRole($adminRole);
                        app(PermissionRegistrar::class)->forgetCachedPermissions();

                        Notification::make()
                            ->title('User promoted to admin')
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
