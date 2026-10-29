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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class BkashPaymentAdapter implements PaymentGatewayContract
{
    public const SANDBOX_BASE_URL = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';

    public const LIVE_BASE_URL = 'https://tokenized.pay.bka.sh/v1.2.0-beta';

    public function getGatewayCode(): string
    {
        return PaymentGatewaySetting::CODE_BKASH;
    }

    public function getBaseUrl(bool $sandbox): string
    {
        return $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
    }

    /**
     * Retrieve credentials and environment configuration.
     *
     * @return array{credentials: array<string, mixed>, is_sandbox: bool}
     */
    protected function resolveSettings(): array
    {
        $setting = PaymentGatewaySetting::where('code', $this->getGatewayCode())->first();
        if (! $setting) {
            throw new RuntimeException('bKash gateway settings are not configured in the database.');
        }

        return [
            'credentials' => is_array($setting->credentials) ? $setting->credentials : [],
            'is_sandbox' => (bool) $setting->is_sandbox,
        ];
    }

    /**
     * Obtain a valid bKash Grant Token (using cache if fresh).
     *
     * @param  array<string, mixed>  $credentials
     */
    public function getGrantToken(array $credentials, bool $sandbox): string
    {
        $appKey = (string) ($credentials['app_key'] ?? '');
        $appSecret = (string) ($credentials['app_secret'] ?? '');
        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        if (empty($appKey) || empty($appSecret) || empty($username) || empty($password)) {
            throw new RuntimeException('Missing required bKash credentials (app_key, app_secret, username, password).');
        }

        $cacheKey = 'bkash_grant_token_'.md5($appKey.($sandbox ? '_sb' : '_live'));
        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && ! empty($cachedToken)) {
            return $cachedToken;
        }

        $baseUrl = $this->getBaseUrl($sandbox);
        $response = Http::withHeaders([
            'username' => $username,
            'password' => $password,
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/tokenized/checkout/token/grant", [
            'app_key' => $appKey,
            'app_secret' => $appSecret,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('bKash grant token request failed: '.$response->body());
        }

        $data = $response->json();
        $idToken = $data['id_token'] ?? null;
        if (! is_string($idToken) || empty($idToken)) {
            $msg = $data['statusMessage'] ?? 'Failed to retrieve id_token from bKash';
            throw new RuntimeException('bKash error: '.$msg);
        }

        // Cache for 50 minutes (tokens typically expire after 60 minutes)
        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $ttlSeconds = max(60, $expiresIn - 600);
        Cache::put($cacheKey, $idToken, $ttlSeconds);

        return $idToken;
    }

    /**
     * Initiate a tokenized payment checkout session with bKash.
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

            $idToken = $this->getGrantToken($creds, $sandbox);
            $baseUrl = $this->getBaseUrl($sandbox);

            $callbackUrl = route('payment.bkash.callback');

            $payload = [
                'mode' => '0011',
                'payerReference' => $order->customer_phone,
                'callbackURL' => $callbackUrl,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $order->order_number,
            ];

            $response = Http::withHeaders([
                'Authorization' => $idToken,
                'X-APP-Key' => $creds['app_key'],
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/tokenized/checkout/create", $payload);

            $data = $response->json();

            if (! $response->successful() || ($data['statusCode'] ?? '') !== '0000') {
                $errMsg = $data['statusMessage'] ?? 'Failed to create bKash checkout session.';

                return PaymentInitiationResultDto::failed($errMsg, metadata: $data ?? []);
            }

            $paymentId = $data['paymentID'] ?? null;
            $bkashUrl = $data['bkashURL'] ?? null;

            if (! $paymentId || ! $bkashUrl) {
                return PaymentInitiationResultDto::failed('Invalid bKash payment initiation response.', metadata: $data);
            }

            // Persist pending transaction
            $trx = PaymentTransaction::create([
                'order_id' => $order->id,
                'gateway_code' => $this->getGatewayCode(),
                'transaction_id' => $paymentId,
                'payment_purpose' => $purpose,
                'amount_bdt' => $amount,
                'gateway_fee_bdt' => 0.0000,
                'currency' => 'BDT',
                'status' => PaymentTransaction::STATUS_PENDING,
                'customer_account' => $order->customer_phone,
                'raw_request_payload' => $payload,
                'raw_response_payload' => $data,
            ]);

            return PaymentInitiationResultDto::successful(
                redirectUrl: $bkashUrl,
                transactionId: $trx->ulid,
                gatewayPaymentId: $paymentId,
                metadata: $data,
            );
        } catch (Throwable $e) {
            return PaymentInitiationResultDto::failed('bKash initiation exception: '.$e->getMessage());
        }
    }

    /**
     * Verify and execute payment upon callback return from bKash.
     */
    public function verifyPayment(Request $request, ?Order $order = null): PaymentVerificationResultDto
    {
        $status = $request->input('status');
        $paymentId = (string) $request->input('paymentID', '');

        $transaction = PaymentTransaction::where('gateway_code', $this->getGatewayCode())
            ->where('transaction_id', $paymentId)
            ->first();

        if ($status !== 'success') {
            $failureReason = match ($status) {
                'cancel' => 'Payment was cancelled by the customer.',
                'failure' => 'Payment failed on bKash.',
                default => 'Unknown payment status: '.(string) $status,
            };

            if ($transaction) {
                $transaction->update([
                    'status' => $status === 'cancel' ? PaymentTransaction::STATUS_CANCELLED : PaymentTransaction::STATUS_FAILED,
                    'error_message' => $failureReason,
                    'raw_response_payload' => $request->all(),
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: $failureReason,
                transactionId: $transaction?->ulid,
                gatewayTrxId: $paymentId,
                rawPayload: $request->all(),
            );
        }

        try {
            $config = $this->resolveSettings();
            $creds = $config['credentials'];
            $sandbox = $config['is_sandbox'];

            $idToken = $this->getGrantToken($creds, $sandbox);
            $baseUrl = $this->getBaseUrl($sandbox);

            $executeResponse = Http::withHeaders([
                'Authorization' => $idToken,
                'X-APP-Key' => $creds['app_key'],
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/tokenized/checkout/execute", [
                'paymentID' => $paymentId,
            ]);

            $data = $executeResponse->json();

            if ($executeResponse->successful() && ($data['statusCode'] ?? '') === '0000' && ($data['transactionStatus'] ?? '') === 'Completed') {
                $trxId = (string) ($data['trxID'] ?? $paymentId);
                $amountPaid = (float) ($data['amount'] ?? 0.00);
                $customerMsisdn = $data['customerMsisdn'] ?? null;

                if ($transaction) {
                    $transaction->update([
                        'status' => PaymentTransaction::STATUS_SUCCESSFUL,
                        'transaction_id' => $trxId,
                        'customer_account' => $customerMsisdn ?? $transaction->customer_account,
                        'raw_response_payload' => $data,
                    ]);
                }

                return PaymentVerificationResultDto::successful(
                    transactionId: $transaction?->ulid ?? $paymentId,
                    gatewayTrxId: $trxId,
                    amountPaid: $amountPaid,
                    customerAccount: $customerMsisdn,
                    rawPayload: $data,
                );
            }

            $errorMsg = $data['statusMessage'] ?? 'bKash execution failed.';
            if ($transaction) {
                $transaction->update([
                    'status' => PaymentTransaction::STATUS_FAILED,
                    'error_message' => $errorMsg,
                    'raw_response_payload' => $data,
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: $errorMsg,
                transactionId: $transaction?->ulid,
                gatewayTrxId: $paymentId,
                rawPayload: $data,
            );
        } catch (Throwable $e) {
            if ($transaction) {
                $transaction->update([
                    'status' => PaymentTransaction::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: 'Exception during bKash verification: '.$e->getMessage(),
                transactionId: $transaction?->ulid,
                gatewayTrxId: $paymentId,
            );
        }
    }

    /**
     * Perform pre-flight API handshake test with bKash.
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

            $token = $this->getGrantToken($effectiveCreds, $sandbox);
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return GatewayConnectionTestResultDto::successful(
                gatewayCode: $this->getGatewayCode(),
                message: 'bKash API handshake successful. Grant token acquired in '.$latencyMs.'ms.',
                latencyMs: $latencyMs,
                details: [
                    'environment' => $sandbox ? 'sandbox' : 'live',
                    'token_preview' => substr($token, 0, 10).'...',
                ],
            );
        } catch (Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return GatewayConnectionTestResultDto::failed(
                gatewayCode: $this->getGatewayCode(),
                message: 'bKash API connection failed: '.$e->getMessage(),
                latencyMs: $latencyMs,
            );
        }
    }
}
