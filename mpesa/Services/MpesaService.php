<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private const RESPONSE_CODE_SUCCESS = 'INS-0';
    private const RESPONSE_CODE_TIMEOUT = 'INS-9';

    /**
     * Provider status labels considered successful when querying payment status.
     *
     * @var array<int, string>
     */
    private const SUCCESS_STATUSES = [
        'COMPLETED',
        'COMPLETE',
        'SUCCESS',
        'SUCCEEDED',
        'PROCESSED',
        'INS-0',
    ];

    /**
     * Provider status labels considered definitive failures when querying status.
     *
     * @var array<int, string>
     */
    private const FAILED_STATUSES = [
        'FAILED',
        'FAIL',
        'CANCELLED',
        'CANCELED',
        'REVERSED',
        'DECLINED',
        'INS-996',
    ];

    private string $apiKey = '';
    private string $publicKeyStr = '';
    private string $baseUrl = '';
    private string $serviceProviderCode = '';
    private bool $verifySsl = true;
    private int $timeoutSeconds = 30;
    private int $connectTimeoutSeconds = 10;

    public function __construct()
    {
        $this->apiKey = (string) config('mpesa.api_key', '');
        $this->publicKeyStr = (string) config('mpesa.public_key', '');
        $this->baseUrl = (string) config('mpesa.base_url', '');
        $this->serviceProviderCode = (string) config('mpesa.service_provider_code', '');
        $this->verifySsl = (bool) config('mpesa.verify_ssl', true);
        $this->timeoutSeconds = max(5, (int) config('mpesa.timeout', 30));
        $this->connectTimeoutSeconds = max(3, (int) config('mpesa.connect_timeout', 10));
    }

    /**
     * Initiate a C2B Payment
     */
    // app/Services/MpesaService.php
    // app/Services/MpesaService.php

    // app/Services/MpesaService.php

    public function initiatePayment(string $phoneNumber, float $amount, string $reference)
    {
        $endpoint = '/ipg/v1x/c2bPayment/singleStage/';
        $baseUrl = str_replace('http://', 'https://', $this->baseUrl);
        $bearerToken = $this->generateAuthorizationToken();

        if (!$bearerToken) {
            return [
                'success' => false,
                'message' => 'Serviço de pagamento indisponível no momento. Tente novamente em alguns minutos.',
            ];
        }

        // 1. Force Formatting
        $formattedAmount = number_format($amount, 2, '.', '');
        $formattedPhone = $this->formatPhoneNumber($phoneNumber); // "25884..."

        $payload = [
            "input_TransactionReference" => $reference,
            "input_CustomerMSISDN" => $formattedPhone,
            "input_Amount" => $formattedAmount,
            "input_ThirdPartyReference" => $reference,
            "input_ServiceProviderCode" => $this->serviceProviderCode,
        ];

        try {
            Log::info('Mpesa C2B request initiated.', [
                'reference_hash' => hash('sha256', $reference),
                'amount' => $formattedAmount,
            ]);

            $response = $this->httpRequest($bearerToken)
                ->post($baseUrl . $endpoint, $payload);

            Log::info('Mpesa C2B response received.', [
                'http_status' => $response->status(),
                'response_code' => $response->json('output_ResponseCode'),
            ]);

            if ($response->successful()) {
                $data = $response->json() ?: [];
                $responseCode = (string) ($data['output_ResponseCode'] ?? '');
                if ($this->isSuccessfulResponseCode($responseCode)) {
                    return [
                        'success' => true,
                        'data' => $data,
                        'transaction_id' => $data['output_TransactionID'] ?? $reference,
                        'response_code' => $responseCode,
                    ];
                }
                return [
                    'success' => false,
                    'message' => $this->userMessageForResponse(
                        $responseCode,
                        isset($data['output_ResponseDesc']) ? (string) $data['output_ResponseDesc'] : null,
                        'payment'
                    ),
                    'response_code' => $responseCode,
                    'data' => $data,
                    'transaction_id' => $data['output_TransactionID'] ?? $reference,
                ];
            }

            $data = $response->json() ?: [];
            $responseCode = isset($data['output_ResponseCode']) ? (string) $data['output_ResponseCode'] : null;
            $providerMessage = isset($data['output_ResponseDesc']) ? (string) $data['output_ResponseDesc'] : null;

            return [
                'success' => false,
                'message' => $this->userMessageForResponse(
                    $responseCode,
                    $providerMessage ?? ('Gateway Error: ' . $response->status()),
                    'payment'
                ),
                'response_code' => $responseCode,
                'data' => is_array($data) ? $data : null,
                'transaction_id' => isset($data['output_TransactionID']) ? (string) $data['output_TransactionID'] : $reference,
                'technical_message' => $providerMessage ?? ('Gateway Error: HTTP ' . $response->status()),
            ];
        } catch (ConnectionException $e) {
            Log::error('Mpesa Connection Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $this->humanizeTransportError($e->getMessage(), 'payment')
                    ?? $this->defaultUserMessage('payment'),
                'technical_message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('Mpesa Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $this->userMessageForResponse(null, $e->getMessage(), 'payment'),
                'technical_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Query the status of a specific transaction
     * Adapted from Java SDK: APIMethodType.GET to /ipg/v1x/queryTransactionStatus/
     * * @param string $queryReference The M-Pesa Transaction ID (e.g., 5C1400CVRO)
     * @param string $thirdPartyReference Your System Reference (e.g., 111PA2D)
     * @return array
     */
    public function queryTransactionStatus(string $queryReference, string $thirdPartyReference)
    {
        $endpoint = '/ipg/v1x/queryTransactionStatus/';

        // Force HTTPS if defined in config logic, consistent with your existing code
        $baseUrl = str_replace('http://', 'https://', $this->baseUrl);

        $bearerToken = $this->generateAuthorizationToken();

        if (!$bearerToken) {
            return [
                'success' => false,
                'message' => 'Serviço de pagamento indisponível no momento. Tente novamente em alguns minutos.',
            ];
        }

        // Java: context.addParameter(...)
        // In Laravel Http::get(), the second argument is the array of query parameters
        $queryParams = [
            "input_ThirdPartyReference" => $thirdPartyReference,
            "input_QueryReference" => $queryReference,
            "input_ServiceProviderCode" => $this->serviceProviderCode,
        ];

        try {
            Log::info('Mpesa transaction status query initiated.', [
                'query_reference_hash' => hash('sha256', $queryReference),
                'third_party_reference_hash' => hash('sha256', $thirdPartyReference),
            ]);

            $response = $this->httpRequest($bearerToken, [
                'debug' => (bool) config('app.debug', false),
            ])
                ->retry(0)
                // Java SDK uses APIMethodType.GET, so we use ->get()
                ->get($baseUrl . $endpoint, $queryParams);

            Log::info('Mpesa transaction status response received.', [
                'http_status' => $response->status(),
                'response_code' => $response->json('output_ResponseCode'),
            ]);

            if ($response->successful()) {
                $data = $response->json() ?: [];
                $responseCode = (string) ($data['output_ResponseCode'] ?? '');
                $transactionStatus = $this->normalizeProviderStatus($data['output_ResponseTransactionStatus'] ?? null);

                // Check for successful response code (INS-0 is standard success)
                if ($this->isSuccessfulResponseCode($responseCode)) {
                    return [
                        'success' => true,
                        'data' => $data,
                        'status' => $transactionStatus,
                        'response_code' => $responseCode,
                    ];
                }

                return [
                    'success' => false,
                    'message' => $this->userMessageForResponse(
                        $responseCode,
                        isset($data['output_ResponseDesc']) ? (string) $data['output_ResponseDesc'] : null,
                        'query'
                    ),
                    'response_code' => $responseCode !== '' ? $responseCode : null,
                    'status' => $transactionStatus !== '' ? $transactionStatus : null,
                    'data' => $data,
                ];
            }

            $data = $response->json() ?: [];
            $responseCode = isset($data['output_ResponseCode']) ? (string) $data['output_ResponseCode'] : null;
            $providerMessage = isset($data['output_ResponseDesc']) ? (string) $data['output_ResponseDesc'] : null;
            return [
                'success' => false,
                'message' => $this->userMessageForResponse(
                    $responseCode,
                    $providerMessage ?? ('Query failed: HTTP ' . $response->status()),
                    'query'
                ),
                'response_code' => $responseCode,
                'status' => isset($data['output_ResponseTransactionStatus'])
                    ? $this->normalizeProviderStatus($data['output_ResponseTransactionStatus'])
                    : null,
                'data' => is_array($data) ? $data : null,
                'technical_message' => $providerMessage ?? ('Query failed: HTTP ' . $response->status()),
            ];
        } catch (ConnectionException $e) {
            Log::error('Mpesa Query Connection Refused: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $this->humanizeTransportError($e->getMessage(), 'query')
                    ?? $this->defaultUserMessage('query'),
                'technical_message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('Mpesa Query General Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $this->userMessageForResponse(null, $e->getMessage(), 'query'),
                'technical_message' => $e->getMessage(),
            ];
        }
    }

    public function normalizeProviderStatus(?string $status): string
    {
        return strtoupper(trim((string) $status));
    }

    public function isSuccessfulResponseCode(?string $responseCode): bool
    {
        return $this->normalizeProviderStatus($responseCode) === self::RESPONSE_CODE_SUCCESS;
    }

    public function isTimeoutResponseCode(?string $responseCode): bool
    {
        return $this->normalizeProviderStatus($responseCode) === self::RESPONSE_CODE_TIMEOUT;
    }

    public function isSuccessfulTransactionStatus(?string $status): bool
    {
        return in_array($this->normalizeProviderStatus($status), self::SUCCESS_STATUSES, true);
    }

    public function isFailedTransactionStatus(?string $status): bool
    {
        return in_array($this->normalizeProviderStatus($status), self::FAILED_STATUSES, true);
    }

    private function httpRequest(string $bearerToken, array $options = []): PendingRequest
    {
        $verifySsl = $this->shouldVerifySsl();

        $requestOptions = array_merge([
            'verify' => $verifySsl,
            'connect_timeout' => $this->connectTimeoutSeconds,
            'timeout' => $this->timeoutSeconds,
        ], $options);

        $request = Http::withOptions($requestOptions)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
                'Origin' => '*',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);

        if (!$verifySsl) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function shouldVerifySsl(): bool
    {
        if (!$this->verifySsl) {
            return false;
        }

        $baseUrl = strtolower($this->baseUrl);

        // Sandbox certificates are often self-signed in local/dev environments.
        if (str_contains($baseUrl, 'sandbox.vm.co.mz')) {
            return false;
        }

        return true;
    }

    private function defaultUserMessage(string $context): string
    {
        return match ($context) {
            'query' => 'Não foi possível confirmar o estado do pagamento neste momento.',
            'reversal' => 'Não foi possível processar a reversão neste momento.',
            default => 'Não foi possível processar o pagamento M-Pesa agora. Tente novamente em alguns minutos.',
        };
    }

    private function looksTechnicalError(string $message): bool
    {
        return (bool) preg_match('/curl|openssl|ssl|certificate|exception|stack trace|gateway error|http \\d{3}/i', $message);
    }

    private function humanizeTransportError(string $technicalMessage, string $context = 'payment'): ?string
    {
        $message = strtolower($technicalMessage);

        if (
            str_contains($message, 'ssl certificate')
            || str_contains($message, 'self-signed')
            || str_contains($message, 'certificate chain')
            || str_contains($message, 'openssl verify')
        ) {
            return 'Não foi possível validar a ligação segura com o serviço de pagamento. Tente novamente em instantes.';
        }

        if (
            str_contains($message, 'curl error 28')
            || str_contains($message, 'operation timed out')
            || str_contains($message, 'timed out')
            || str_contains($message, 'request timeout')
        ) {
            return $context === 'query'
                ? 'A confirmação do pagamento está a demorar mais que o esperado. Tente novamente em alguns minutos.'
                : 'A ligação ao M-Pesa está lenta neste momento. Tente novamente em alguns minutos.';
        }

        if (
            str_contains($message, 'could not resolve host')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'network is unreachable')
        ) {
            return 'Não foi possível ligar ao serviço M-Pesa neste momento. Tente novamente em alguns minutos.';
        }

        return null;
    }

    private function userMessageForResponse(?string $responseCode, ?string $providerMessage, string $context = 'payment'): string
    {
        $code = $this->normalizeProviderStatus($responseCode);

        $mappedByCode = match ($code) {
            'INS-2' => 'Não foi possível autenticar o serviço de pagamento. Tente novamente em alguns minutos.',
            'INS-9' => 'A confirmação do pagamento está a demorar. Se já inseriu o PIN, aguarde alguns minutos.',
            'INS-10' => 'Já existe um pedido de pagamento em processamento para este número. Confirme no telemóvel e aguarde.',
            'INS-15' => 'O valor do pagamento é inválido. Tente novamente.',
            'INS-2051' => 'Número M-Pesa inválido. Verifique o número e tente novamente.',
            'INS-2006' => 'Saldo insuficiente na conta M-Pesa.',
            'INS-996' => 'A transação foi cancelada pelo operador.',
            default => null,
        };

        if ($mappedByCode !== null) {
            return $mappedByCode;
        }

        if (is_string($providerMessage) && trim($providerMessage) !== '') {
            $fromTransport = $this->humanizeTransportError($providerMessage, $context);
            if ($fromTransport !== null) {
                return $fromTransport;
            }

            if (!$this->looksTechnicalError($providerMessage)) {
                return trim($providerMessage);
            }
        }

        return $this->defaultUserMessage($context);
    }

    /**
     * Initiate a B2B Payment
     * Endpoint: /ipg/v1x/b2bPayment/
     */
    public function b2bPayment(string $amount, string $receiverPartyCode, string $reference, string $thirdPartyReference)
    {
        $endpoint = '/ipg/v1x/b2bPayment/';

        // 1. Force HTTPS
        $url = str_replace('http://', 'https://', $this->baseUrl);

        // 2. FORCE PORT 18349 (Critical for B2B)
        // This swaps out the C2B port (18352) or B2C port (18345) for the correct B2B port.
        if (preg_match('/183(52|45|54)/', $url)) {
            $url = preg_replace('/183(52|45|54)/', '18349', $url);
        }

        $bearerToken = $this->generateAuthorizationToken();

        if (!$bearerToken) {
            return [
                'success' => false,
                'message' => 'Serviço de pagamento indisponível no momento. Tente novamente em alguns minutos.',
            ];
        }

        $payload = [
            "input_TransactionReference" => $reference,
            "input_Amount" => (string)$amount,
            "input_ThirdPartyReference" => $thirdPartyReference,
            "input_PrimaryPartyCode" => $this->serviceProviderCode, // 171717
            "input_ReceiverPartyCode" => $receiverPartyCode,        // Try 979797 if 902809 fails
        ];

        try {
            Log::info('Mpesa B2B request initiated.', [
                'reference_hash' => hash('sha256', $reference),
                'third_party_reference_hash' => hash('sha256', $thirdPartyReference),
            ]);

            $response = $this->httpRequest($bearerToken)
                ->post($url . $endpoint, $payload);

            Log::info('Mpesa B2B response received.', [
                'http_status' => $response->status(),
                'response_code' => $response->json('output_ResponseCode'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // INS-0 means accepted/successful
                if (isset($data['output_ResponseCode']) && $data['output_ResponseCode'] === 'INS-0') {
                    return [
                        'success' => true,
                        'data' => $data,
                        'transaction_id' => $data['output_TransactionID'] ?? null
                    ];
                }

                return [
                    'success' => false,
                    'message' => $this->userMessageForResponse(
                        isset($data['output_ResponseCode']) ? (string) $data['output_ResponseCode'] : null,
                        isset($data['output_ResponseDesc']) ? (string) $data['output_ResponseDesc'] : null,
                        'payment'
                    ),
                ];
            }

            return [
                'success' => false,
                'message' => $this->defaultUserMessage('payment'),
                'technical_message' => 'Request failed: HTTP ' . $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("Mpesa B2B Error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $this->userMessageForResponse(null, $e->getMessage(), 'payment'),
                'technical_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Initiate a B2C Payment (Business to Customer)
     * Endpoint: /ipg/v1x/b2cPayment/
     * Port (Sandbox): 18345
     */
    public function b2cPayment(string $phoneNumber, float $amount, string $reference, string $thirdPartyReference)
    {
        $endpoint = '/ipg/v1x/b2cPayment/';

        // 1. Force HTTPS
        $url = str_replace('http://', 'https://', $this->baseUrl);

        // 2. Handle Sandbox Port Specifics (18345 for B2C)
        // If your .env has port 18352 (C2B), we try to swap it for 18345 for this call.
        if (str_contains($url, '18352') || str_contains($url, '18349')) {
            $url = str_replace(['18352', '18349'], '18345', $url);
        }

        $bearerToken = $this->generateAuthorizationToken();

        if (!$bearerToken) {
            return [
                'success' => false,
                'message' => 'Serviço de pagamento indisponível no momento. Tente novamente em alguns minutos.',
            ];
        }

        $payload = [
            "input_TransactionReference" => $reference,
            "input_CustomerMSISDN" => $this->formatPhoneNumber($phoneNumber), // e.g. 258841234567
            "input_Amount" => (string)$amount,
            "input_ThirdPartyReference" => $thirdPartyReference,
            "input_ServiceProviderCode" => $this->serviceProviderCode,
        ];

        try {
            Log::info('Mpesa B2C request initiated.', [
                'reference_hash' => hash('sha256', $reference),
                'third_party_reference_hash' => hash('sha256', $thirdPartyReference),
            ]);

            $response = $this->httpRequest($bearerToken)
                ->post($url . $endpoint, $payload);

            Log::info('Mpesa B2C response received.', [
                'http_status' => $response->status(),
                'response_code' => $response->json('output_ResponseCode'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['output_ResponseCode']) && $data['output_ResponseCode'] === 'INS-0') {
                    return [
                        'success' => true,
                        'data' => $data,
                        'transaction_id' => $data['output_TransactionID'] ?? null
                    ];
                }

                return [
                    'success' => false,
                    'message' => $this->userMessageForResponse(
                        isset($data['output_ResponseCode']) ? (string) $data['output_ResponseCode'] : null,
                        isset($data['output_ResponseDesc']) ? (string) $data['output_ResponseDesc'] : null,
                        'payment'
                    ),
                ];
            }

            return [
                'success' => false,
                'message' => $this->defaultUserMessage('payment'),
                'technical_message' => 'Request failed: HTTP ' . $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("Mpesa B2C Error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $this->userMessageForResponse(null, $e->getMessage(), 'payment'),
                'technical_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reverse a successful transaction
     * Endpoint: /ipg/v1x/reversal/
     * Method: PUT
     * Port (Sandbox): 18354
     */
    public function reverseTransaction(
        string $transactionId,
        float $amount,
        string $thirdPartyReference,
        string $securityCredential = 'Mpesa2019', // Default Sandbox Credential
        string $initiatorIdentifier = 'MPesa2018' // Default Sandbox Initiator
    ) {
        $endpoint = '/ipg/v1x/reversal/';

        // 1. Force HTTPS
        $url = str_replace('http://', 'https://', $this->baseUrl);

        // 2. Handle Sandbox Port (18354 for Reversals)
        // Replaces common ports (18352/18349/18345) with 18354
        if (preg_match('/183(52|49|45)/', $url)) {
            $url = preg_replace('/183(52|49|45)/', '18354', $url);
        }

        $bearerToken = $this->generateAuthorizationToken();

        if (!$bearerToken) {
            return [
                'success' => false,
                'message' => 'Serviço de pagamento indisponível no momento. Tente novamente em alguns minutos.',
            ];
        }

        $payload = [
            "input_TransactionID" => $transactionId,
            "input_SecurityCredential" => $securityCredential,
            "input_InitiatorIdentifier" => $initiatorIdentifier,
            "input_ThirdPartyReference" => $thirdPartyReference,
            "input_ServiceProviderCode" => $this->serviceProviderCode,
            "input_ReversalAmount" => (string)$amount,
        ];

        try {
            Log::info('Mpesa reversal request initiated.', [
                'transaction_id_hash' => hash('sha256', $transactionId),
                'third_party_reference_hash' => hash('sha256', $thirdPartyReference),
            ]);

            $response = $this->httpRequest($bearerToken, [
                'timeout' => max(60, $this->timeoutSeconds),
            ])
                ->put($url . $endpoint, $payload); // NOTE: Reversal uses PUT

            Log::info('Mpesa reversal response received.', [
                'http_status' => $response->status(),
                'response_code' => $response->json('output_ResponseCode'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['output_ResponseCode']) && $data['output_ResponseCode'] === 'INS-0') {
                    return [
                        'success' => true,
                        'data' => $data,
                        'transaction_id' => $data['output_TransactionID'] ?? null
                    ];
                }

                return [
                    'success' => false,
                    'message' => $this->userMessageForResponse(
                        isset($data['output_ResponseCode']) ? (string) $data['output_ResponseCode'] : null,
                        isset($data['output_ResponseDesc']) ? (string) $data['output_ResponseDesc'] : null,
                        'reversal'
                    ),
                ];
            }

            return [
                'success' => false,
                'message' => $this->defaultUserMessage('reversal'),
                'technical_message' => 'Request failed: HTTP ' . $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("Mpesa Reversal Error: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $this->userMessageForResponse(null, $e->getMessage(), 'reversal'),
                'technical_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Encrypts the API Key using the Public Key to create the Session ID/Token
     */
    private function generateAuthorizationToken(): ?string
    {
        try {
            // 1. Clean the key: remove headers if they exist in the env, remove spaces/newlines
            $cleanKey = trim(str_replace(
                ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r", " "],
                '',
                $this->publicKeyStr
            ));

            // 2. Re-format specifically for OpenSSL (64 chars per line)
            $formattedKey = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($cleanKey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";

            // 3. Validate the key resource
            $publicKeyResource = openssl_get_publickey($formattedKey);
            if (!$publicKeyResource) {
                Log::error("Mpesa Token Error: Invalid Public Key format.");
                return null;
            }

            // 4. Encrypt using PKCS1 padding (Standard for M-Pesa)
            $encrypted = '';
            $success = openssl_public_encrypt(
                $this->apiKey,
                $encrypted,
                $publicKeyResource,
                OPENSSL_PKCS1_PADDING
            );

            if ($success) {
                return base64_encode($encrypted);
            }

            Log::error("Mpesa Token Error: OpenSSL encryption failed: " . openssl_error_string());
            return null;
        } catch (\Exception $e) {
            Log::error("Mpesa Token Gen Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Ensure phone number is in 258xxxxxxxxx format
     */
    private function formatPhoneNumber(string $number): string
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        if (strlen($number) === 9) {
            return '258' . $number;
        }

        return $number;
    }
}
