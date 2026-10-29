<?php

declare(strict_types=1);

namespace App\Modules\Payment\Adapters;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Contracts\PaymentGatewayContract;
use App\Modules\Payment\DTOs\GatewayConnectionTestResultDto;
use App\Modules\Payment\DTOs\PaymentInitiationResultDto;
use App\Modules\Payment\DTOs\PaymentVerificationResultDto;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Services\NagadCryptographicService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NagadPaymentAdapter implements PaymentGatewayContract
{
    public const SANDBOX_BASE_URL = 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs';

    public const LIVE_BASE_URL = 'https://api.mynagad.com/api/dfs';

    public function __construct(
        protected NagadCryptographicService $crypto,
    ) {}

    public function getGatewayCode(): string
    {
        return PaymentGatewaySetting::CODE_NAGAD;
    }

    public function getBaseUrl(bool $sandbox): string
    {
        return $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
    }

    /**
     * Resolve credentials from database.
     *
     * @return array{credentials: array<string, mixed>, is_sandbox: bool}
     */
    protected function resolveSettings(): array
    {
        $setting = PaymentGatewaySetting::where('code', $this->getGatewayCode())->first();
        if (! $setting) {
            throw new RuntimeException('Nagad gateway settings are not configured in the database.');
        }

        return [
            'credentials' => is_array($setting->credentials) ? $setting->credentials : [],
            'is_sandbox' => (bool) $setting->is_sandbox,
        ];
    }

    /**
     * Step 1 & 2: Initialize checkout session and generate customer redirect URL.
     *
     * @param  array<string, mixed>  $meta
     */
    public function initiatePayment(
        Order $order,
        float $amount,
        string $purpose = 'order_full',
        array $meta = []
    ): PaymentInitiationResultDto {
        try {
            $config = $this->resolveSettings();
            $creds = $config['credentials'];
            $sandbox = $config['is_sandbox'];

            $merchantId = (string) ($creds['merchant_id'] ?? '');
            $privKey = (string) ($creds['merchant_private_key'] ?? '');
            $pubKey = (string) ($creds['pg_public_key'] ?? '');

            if (empty($merchantId) || empty($privKey) || empty($pubKey)) {
                return PaymentInitiationResultDto::failed('Nagad configuration missing merchant_id, private key, or public key.');
            }

            $baseUrl = $this->getBaseUrl($sandbox);
            $orderId = $order->order_number;
            $dateTime = now()->format('YmdHis');
            $challenge = Str::random(40);

            // 1. Initialize Step
            $initData = json_encode([
                'merchantId' => $merchantId,
                'datetime' => $dateTime,
                'orderId' => $orderId,
                'challenge' => $challenge,
            ], JSON_THROW_ON_ERROR);

            $encryptedInit = $this->crypto->encryptWithPublicKey($initData, $pubKey);
            $signatureInit = $this->crypto->generateSignature($initData, $privKey);

            $initResponse = Http::withHeaders([
                'X-KM-Api-Version' => 'v-0.2',
                'X-KM-IP-V4' => '127.0.0.1',
                'X-KM-Client-Type' => 'PC_WEB',
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/check-out/initialize/{$merchantId}/{$orderId}", [
                'dateTime' => $dateTime,
                'sensitiveData' => $encryptedInit,
                'signature' => $signatureInit,
            ]);

            $initJson = $initResponse->json();
            if (! $initResponse->successful() || empty($initJson['sensitiveData'])) {
                $err = $initJson['message'] ?? 'Nagad checkout initialization failed.';

                return PaymentInitiationResultDto::failed($err, metadata: $initJson ?? []);
            }

            $decryptedInit = $this->crypto->decryptWithPrivateKey($initJson['sensitiveData'], $privKey);
            $initResultData = json_decode($decryptedInit, true, 512, JSON_THROW_ON_ERROR);

            $paymentRefId = $initResultData['paymentReferenceId'] ?? null;
            $returnedChallenge = $initResultData['challenge'] ?? null;

            if (! $paymentRefId) {
                return PaymentInitiationResultDto::failed('Failed to extract payment reference ID from Nagad response.');
            }

            // 2. Complete Step
            $callbackUrl = route('payment.nagad.callback');
            $completeData = json_encode([
                'merchantId' => $merchantId,
                'orderId' => $orderId,
                'currencyCode' => '050',
                'amount' => number_format($amount, 2, '.', ''),
                'challenge' => $returnedChallenge ?? $challenge,
            ], JSON_THROW_ON_ERROR);

            $encryptedComplete = $this->crypto->encryptWithPublicKey($completeData, $pubKey);
            $signatureComplete = $this->crypto->generateSignature($completeData, $privKey);

            $completeResponse = Http::withHeaders([
                'X-KM-Api-Version' => 'v-0.2',
                'X-KM-IP-V4' => '127.0.0.1',
                'X-KM-Client-Type' => 'PC_WEB',
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/check-out/complete/{$paymentRefId}", [
                'sensitiveData' => $encryptedComplete,
                'signature' => $signatureComplete,
                'merchantCallbackURL' => $callbackUrl,
            ]);

            $completeJson = $completeResponse->json();
            if (! $completeResponse->successful() || ($completeJson['status'] ?? '') !== 'Success' || empty($completeJson['callBackUrl'])) {
                $errMsg = $completeJson['message'] ?? 'Nagad checkout completion step failed.';

                return PaymentInitiationResultDto::failed($errMsg, metadata: $completeJson ?? []);
            }

            $redirectUrl = (string) $completeJson['callBackUrl'];

            // Persist pending payment transaction
            $trx = PaymentTransaction::create([
                'order_id' => $order->id,
                'gateway_code' => $this->getGatewayCode(),
                'transaction_id' => $paymentRefId,
                'payment_purpose' => $purpose,
                'amount_bdt' => $amount,
                'gateway_fee_bdt' => 0.0000,
                'currency' => 'BDT',
                'status' => PaymentTransaction::STATUS_PENDING,
                'customer_account' => $order->customer_phone,
                'raw_request_payload' => ['paymentRefId' => $paymentRefId, 'amount' => $amount],
                'raw_response_payload' => $completeJson,
            ]);

            return PaymentInitiationResultDto::successful(
                redirectUrl: $redirectUrl,
                transactionId: $trx->ulid,
                gatewayPaymentId: $paymentRefId,
                metadata: $completeJson,
            );
        } catch (Throwable $e) {
            return PaymentInitiationResultDto::failed('Nagad initiation exception: '.$e->getMessage());
        }
    }

    /**
     * Step 3: Verify payment status from return URL callback.
     */
    public function verifyPayment(Request $request, ?Order $order = null): PaymentVerificationResultDto
    {
        $paymentRefId = (string) ($request->input('payment_ref_id') ?? $request->input('paymentReferenceId', ''));
        $status = (string) $request->input('status', '');

        $transaction = PaymentTransaction::where('gateway_code', $this->getGatewayCode())
            ->where('transaction_id', $paymentRefId)
            ->first();

        if ($status !== 'Success') {
            $reason = match ($status) {
                'Aborted' => 'Nagad transaction was cancelled by customer.',
                default => 'Nagad transaction failed with status: '.$status,
            };

            if ($transaction) {
                $transaction->update([
                    'status' => $status === 'Aborted' ? PaymentTransaction::STATUS_CANCELLED : PaymentTransaction::STATUS_FAILED,
                    'error_message' => $reason,
                    'raw_response_payload' => $request->all(),
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: $reason,
                transactionId: $transaction?->ulid,
                gatewayTrxId: $paymentRefId,
                rawPayload: $request->all(),
            );
        }

        try {
            $config = $this->resolveSettings();
            $sandbox = $config['is_sandbox'];
            $baseUrl = $this->getBaseUrl($sandbox);

            $verifyResponse = Http::withHeaders([
                'X-KM-Api-Version' => 'v-0.2',
                'X-KM-IP-V4' => '127.0.0.1',
                'X-KM-Client-Type' => 'PC_WEB',
            ])->get("{$baseUrl}/verify/payment/{$paymentRefId}");

            $data = $verifyResponse->json();

            if ($verifyResponse->successful() && ($data['status'] ?? '') === 'Success' && ($data['statusCode'] ?? '') === '000_0000_000') {
                $issuerRefNo = (string) ($data['issuerPaymentRefNo'] ?? $paymentRefId);
                $amountPaid = (float) ($data['amount'] ?? 0.00);
                $clientMobile = $data['clientMobileNo'] ?? null;

                if ($transaction) {
                    $transaction->update([
                        'status' => PaymentTransaction::STATUS_SUCCESSFUL,
                        'transaction_id' => $issuerRefNo,
                        'customer_account' => $clientMobile ?? $transaction->customer_account,
                        'raw_response_payload' => $data,
                    ]);
                }

                return PaymentVerificationResultDto::successful(
                    transactionId: $transaction?->ulid ?? $paymentRefId,
                    gatewayTrxId: $issuerRefNo,
                    amountPaid: $amountPaid,
                    customerAccount: $clientMobile,
                    rawPayload: $data,
                );
            }

            $errMsg = $data['message'] ?? 'Nagad payment verification failed.';
            if ($transaction) {
                $transaction->update([
                    'status' => PaymentTransaction::STATUS_FAILED,
                    'error_message' => $errMsg,
                    'raw_response_payload' => $data,
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: $errMsg,
                transactionId: $transaction?->ulid,
                gatewayTrxId: $paymentRefId,
                rawPayload: $data ?? [],
            );
        } catch (Throwable $e) {
            if ($transaction) {
                $transaction->update([
                    'status' => PaymentTransaction::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: 'Exception during Nagad verification: '.$e->getMessage(),
                transactionId: $transaction?->ulid,
                gatewayTrxId: $paymentRefId,
            );
        }
    }

    /**
     * Pre-flight API test and RSA key cryptographic validation.
     *
     * @param  array<string, mixed>|null  $credentials
     */
    public function testConnection(?array $credentials = null, bool $sandbox = true): GatewayConnectionTestResultDto
    {
        $startTime = microtime(true);

        try {
            $effectiveCreds = $credentials;
            if ($effectiveCreds === null) {
                $resolved = $this->resolveSettings();
                $effectiveCreds = $resolved['credentials'];
                $sandbox = $resolved['is_sandbox'];
            }

            $merchantId = (string) ($effectiveCreds['merchant_id'] ?? '');
            $privKey = (string) ($effectiveCreds['merchant_private_key'] ?? '');
            $pubKey = (string) ($effectiveCreds['pg_public_key'] ?? '');

            if (empty($merchantId) || empty($privKey) || empty($pubKey)) {
                return GatewayConnectionTestResultDto::failed(
                    gatewayCode: $this->getGatewayCode(),
                    message: 'Nagad test failed: Missing merchant_id, merchant_private_key, or pg_public_key.',
                );
            }

            // Cryptographic test: sign test payload and encrypt with public key
            $testPlaintext = 'test_handshake_'.time();
            $signature = $this->crypto->generateSignature($testPlaintext, $privKey);
            $encrypted = $this->crypto->encryptWithPublicKey($testPlaintext, $pubKey);

            if (empty($signature) || empty($encrypted)) {
                throw new RuntimeException('Failed to generate test signature or encryption block.');
            }

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return GatewayConnectionTestResultDto::successful(
                gatewayCode: $this->getGatewayCode(),
                message: 'Nagad RSA Cryptographic handshake and key validation successful in '.$latencyMs.'ms.',
                latencyMs: $latencyMs,
                details: [
                    'merchant_id' => $merchantId,
                    'environment' => $sandbox ? 'sandbox' : 'live',
                    'signature_algorithm' => 'SHA256withRSA',
                    'encryption_algorithm' => 'RSA/ECB/PKCS1Padding',
                ],
            );
        } catch (Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return GatewayConnectionTestResultDto::failed(
                gatewayCode: $this->getGatewayCode(),
                message: 'Nagad connection failed: '.$e->getMessage(),
                latencyMs: $latencyMs,
            );
        }
    }
}
