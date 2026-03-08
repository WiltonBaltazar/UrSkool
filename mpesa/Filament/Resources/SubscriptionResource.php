<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\Subscription;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Services\MpesaService;
use Filament\Notifications\Notification;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'User Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'first_name')
                    ->required(),
                Forms\Components\Select::make('plan_id')
                    ->relationship('plan', 'name')
                    ->required(),
                Forms\Components\DateTimePicker::make('start_date')
                    ->default(now())
                    ->required(),
                Forms\Components\DateTimePicker::make('end_date')
                    ->required(),
                Forms\Components\DateTimePicker::make('trial_ends_at'),
                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ])->required(),
                Forms\Components\DateTimePicker::make('canceled_at'),
                Forms\Components\TextInput::make('payment_status')
                    ->default('unpaid')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'plan']))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('canceled_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->searchable(),
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
                Tables\Actions\Action::make('verify_payment')
                    ->label('Verify Payment')
                    ->icon('heroicon-m-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->action(function (Subscription $record, MpesaService $mpesaService) {
                    
                    // 1. Call API
                    $response = $mpesaService->queryTransactionStatus(
                        $record->mpesa_transaction_id ?? $record->payment_reference,
                        $record->payment_reference
                    );

                    if (!$response['success']) {
                        Notification::make()
                            ->title('Verification Failed')
                            ->body($response['message'])
                            ->danger()
                            ->send();
                        return;
                    }

                    // 2. Update Record Logic
                    $status = strtoupper($response['status']);
                    
                    if (in_array($status, ['COMPLETED', 'SUCCESS', 'INS-0'])) {
                        // Ensure it is marked paid
                        $record->update(['payment_status' => 'paid', 'status' => 'active']);
                        
                        Notification::make()
                            ->title('Payment Verified')
                            ->body("Transaction confirmed: $status")
                            ->success()
                            ->send();
                    } else {
                        // Mark as failed
                        $record->update(['payment_status' => 'failed', 'status' => 'cancelled']);
                        
                        Notification::make()
                            ->title('Payment Invalid')
                            ->body("M-Pesa returned status: $status. Subscription cancelled.")
                            ->danger()
                            ->send();
                    }
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
