<?php

namespace App\Filament\Pages;

use App\Services\MpesaService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class MpesaOperations extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'M-Pesa Operations';
    protected static ?string $title = 'M-Pesa Transactions';
    protected static string $view = 'filament.pages.mpesa-operations';

    // 1. Separate Data Arrays
    public ?array $b2bData = [];
    public ?array $b2cData = [];
    // 1. Add Property
    public ?array $reversalData = [];

    public function mount(): void
    {
        // Initialize distinct forms
        $this->b2bForm->fill(['reference' => 'B2B' . strtoupper(Str::random(6))]);
        $this->b2cForm->fill(['reference' => 'B2C' . strtoupper(Str::random(6))]);
        $this->reversalForm->fill([
            // Reversals don't strictly need a unique ThirdPartyRef, but it's good practice
            'reference' => 'REV' . strtoupper(Str::random(6))
        ]);
    }

    // 2. Register Both Forms
    protected function getForms(): array
    {
        return [
            'b2bForm',
            'b2cForm',
            'reversalForm',
        ];
    }

    // --- FORM 1: B2B ---
    public function b2bForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('amount')
                    ->label('Amount (MZN)')
                    ->numeric()
                    ->prefix('MT')
                    ->required(), // This required ONLY applies to B2B now

                TextInput::make('receiver_code')
                    ->label('Receiver Party Code')
                    ->numeric()
                    ->required(),

                TextInput::make('reference')
                    ->readOnly(),
            ])
            ->statePath('b2bData'); // Binds to $b2bData
    }

    // --- FORM 2: B2C ---
    public function b2cForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('amount')
                    ->label('Amount (MZN)')
                    ->numeric()
                    ->prefix('MT')
                    ->required(), // This required ONLY applies to B2C now

                TextInput::make('customer_phone')
                    ->label('Customer Phone')
                    ->tel()
                    ->required(),

                TextInput::make('reference')
                    ->readOnly(),
            ])
            ->statePath('b2cData'); // Binds to $b2cData
    }

    public function submitB2B()
    {
        $data = $this->b2bForm->getState();

        $mpesa = new MpesaService();
        $result = $mpesa->b2bPayment(
            $data['amount'],
            $data['receiver_code'], // Try using 979797 here first
            $data['reference'],

            // FIX: Remove hyphen! Use alphanumeric only.
            'SYS' . strtoupper(Str::random(6))
        );

        $this->handleResponse($result, 'b2b');
    }

    public function submitB2C()
    {
        // getState() validates ONLY the b2cForm schema
        $data = $this->b2cForm->getState();

        $mpesa = new MpesaService();

        $result = $mpesa->b2cPayment(
            $data['customer_phone'],
            $data['amount'],
            $data['reference'], // This is input_TransactionReference (e.g. B2C123456)

            // FIX: Remove the hyphen here. Use alphanumeric only.
            'REF' . strtoupper(Str::random(6)) // This is input_ThirdPartyReference
        );

        $this->handleResponse($result, 'b2c');
    }

    // 4. Define Reversal Form
    public function reversalForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('transaction_id')
                    ->label('Original Transaction ID')
                    ->helperText('The M-Pesa ID to reverse (e.g. 49XCDF6)')
                    ->required(),

                TextInput::make('amount')
                    ->label('Amount to Reverse (MZN)')
                    ->numeric()
                    ->prefix('MT')
                    ->required(),

                TextInput::make('reference')
                    ->label('Reversal Reference')
                    ->readOnly(),

                // Optional: Allow Admin to override credentials if needed
                TextInput::make('security_credential')
                    ->label('Security Credential')
                    ->default('Mpesa2019') // Sandbox Default
                    ->password()
                    ->revealable(),
            ])
            ->statePath('reversalData');
    }

    // 5. Submit Action
    public function submitReversal()
    {
        // 1. Get Data
        $data = $this->reversalForm->getState();

        // 2. Call Service
        $mpesa = new MpesaService();
        $result = $mpesa->reverseTransaction(
            $data['transaction_id'],
            $data['amount'],
            $data['reference'],
            $data['security_credential'] ?? 'Mpesa2019'
        );

        // 3. Delegate to Helper
        $this->handleResponse($result, 'reversal');
    }

    private function handleResponse($result, $type)
    {
        if ($result['success']) {
            Notification::make()
                ->title(ucfirst($type) . ' Successful') // e.g. "Reversal Successful"
                ->body('Tx ID: ' . $result['transaction_id'])
                ->success()
                ->send();

            // Reset the specific form based on type
            if ($type === 'b2b') {
                $this->b2bForm->fill(['reference' => 'B2B' . strtoupper(Str::random(6))]);
            } elseif ($type === 'b2c') {
                $this->b2cForm->fill(['reference' => 'B2C' . strtoupper(Str::random(6))]);
            } elseif ($type === 'reversal') {
                $this->reversalForm->fill(['reference' => 'REV' . strtoupper(Str::random(6))]);
            }
        } else {
            Notification::make()
                ->title('Failed')
                ->body($result['message'])
                ->danger()
                ->send();
        }
    }
}
