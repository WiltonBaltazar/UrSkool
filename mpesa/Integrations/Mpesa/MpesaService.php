<?php

namespace App\Integrations\Mpesa;

use App\Exceptions\LendaException;
use App\Integrations\Mpesa\Enums\MpesaResponseCodes;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MpesaService
{
    private const MPESA_MAX_AMOUNT = 45000;

    /**
     * Get a valid Session ID (Token) from M-Pesa
     * Corresponds to the internal auth logic of the Java SDK
     */
    private function getSession()
    {
        return Cache::remember('mpesa_session_token', 600, function () {
            
            $apiKey = app('env') == 'production' 
                ? config('lenda.mpesa.prod_key') 
                : config('lenda.mpesa.test_key');
            
            // Java Code: context.setAddress("api.sandbox.vm.co.mz") + port 18352
            $url = app('env') == 'production' 
                ? 'https://api.vm.co.mz:18352/ipg/v1x/getSession/' 
                : 'https://api.sandbox.vm.co.mz:18352/ipg/v1x/getSession/';

            $client = new Client();
            $verifySsl = (bool) config('mpesa.verify_ssl', true);

            try {
                $response = $client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        // Java Code: context.addHeader("Origin", "*")
                        'Origin'        => '*', 
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'verify' => $verifySsl
                ]);

                $body = json_decode($response->getBody());
                
                if (isset($body->output_SessionID)) {
                    return $body->output_SessionID;
                }
                
                Log::error("M-Pesa Login Failed: " . json_encode($body));
                throw new Exception("No Session ID returned");

            } catch (Exception $e) {
                Cache::forget('mpesa_session_token');
                Log::error("M-Pesa Auth Error: " . $e->getMessage());
                throw new LendaException(json_encode(['message' => 'Erro de autenticação M-Pesa.']), 503);
            }
        });
    }

    /**
     * Helper to make the HTTP Request
     * Matches Java: APIRequest request = new APIRequest(context); request.execute();
     */
    private function initializeRequest($serviceURL, $fields)
    {
        $sessionToken = $this->getSession();
        $client = new Client();
        $verifySsl = (bool) config('mpesa.verify_ssl', true);

        $headers = [
            'Authorization' => 'Bearer ' . $sessionToken,
            'Content-Type' => 'application/json',
            'Origin' => '*' // Matches Java Code
        ];

        try {
            $result = $client->post($serviceURL, [
                'headers' => $headers,
                'json' => $fields,
                'verify' => $verifySsl
            ]);
        } catch (Exception $e) {
            $message = $e->getMessage();
            Log::error("Error from Mpesa: " . $message);
            Cache::forget('mpesa_session_token');
            throw new LendaException(json_encode(['message' => 'Erro na transação M-Pesa.']), 503);
        }

        $rawBody = (string) $result->getBody();
        $decodedBody = json_decode($rawBody);

        Log::debug('Mpesa response received.', [
            'response_code' => is_object($decodedBody) ? ($decodedBody->output_ResponseCode ?? null) : null,
        ]);

        return $decodedBody;
    }

    private function c2b(string $contact, float|string $amount, string $transactionType)
    {
        // Java Code: context.setPath("/ipg/v1x/c2bPayment/singleStage/")
        $url = app('env') == 'production' 
            ? 'https://api.vm.co.mz:18352/ipg/v1x/c2bPayment/singleStage/' 
            : "https://api.sandbox.vm.co.mz:18352/ipg/v1x/c2bPayment/singleStage/";

        // Java Code: context.addParameter(...)
        $fields = [
            'input_TransactionReference' => $transactionType,
            'input_CustomerMSISDN' => '258' . $contact,
            'input_Amount' => (string)$amount, // Ensure string format
            'input_ThirdPartyReference' => $this->generateThirdParty(10),
            'input_ServiceProviderCode' => app('env') == 'production' ? '999278' : '171717'
        ];

        $response = $this->initializeRequest($url, $fields);
        return $response;
    }

    // Keep your logic for B2C, generateThirdParty, and checkResult exactly as they were
    // Just ensure B2C also uses $this->initializeRequest($url, $fields)

    private function b2c(string $contact, float|string $amount, string $transactionType)
    {
        $url = app('env') == 'production' 
            ? 'https://api.vm.co.mz:18345/ipg/v1x/b2cPayment/' 
            : "https://api.sandbox.vm.co.mz:18345/ipg/v1x/b2cPayment/";

        $fields = [
            'input_TransactionReference' => $transactionType,
            'input_CustomerMSISDN' => '258' . $contact,
            'input_Amount' => (string)$amount,
            'input_ThirdPartyReference' => $this->generateThirdParty(10),
            'input_ServiceProviderCode' => app('env') == 'production' ? '999278' : '171717'
        ];

        return $this->initializeRequest($url, $fields);
    }

    private function generateThirdParty($length)
    {
        $chars = "122346789ABCDEFGHIJKLMNOPQRRSTUVWXYZ987";
        $code = '';
        $total = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[rand(0, $total)];
        }
        return $code;
    }

    public function topUpWithMpesa(string $contact, float $amount, string $transactionType)
    {
        $data = $this->c2b($contact, $amount, $transactionType);
        if (!$data) return false;
        
        return $this->checkResult($data->output_ResponseCode ?? 'FAILED');
    }

    public function withdrawWithMpesa(string $contact, float $amount, string $transactionType)
    {
        $data = $this->b2c($contact, $amount, $transactionType);
        if (!$data) return false;

        return $this->checkResult($data->output_ResponseCode ?? 'FAILED');
    }

    private function checkResult($result)
    {
        if ($result === MpesaResponseCodes::PROCESSED_SUCESSFULLY->value) {
            return true;
        } else if ($result === MpesaResponseCodes::INVALID_AMOUNT->value) {
            throw new LendaException(json_encode(['message' => __('mpesa.invalid_amount')]), 422);
        } else if ($result === MpesaResponseCodes::INVALID_CONTACT->value) {
            throw new LendaException(json_encode(['message' => __('mpesa.invalid_contact')]), 422);
        } else if ($result === MpesaResponseCodes::REQUEST_TIMEOUT->value) {
            throw new LendaException(json_encode(['message' =>  __('mpesa.request_timeout')]), 422);
        } else if ($result === MpesaResponseCodes::NOT_ENOUGH_BALANCE->value) {
            throw new LendaException(json_encode(['message' =>  __('mpesa.not_enough_balance')]), 422);
        } else {
            return false;
        }
    }
}
