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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SslcommerzPaymentAdapter implements PaymentGatewayContract
{
    public const SANDBOX_BASE_URL = 'https://sandbox.sslcommerz.com';

    public const LIVE_BASE_URL = 'https://securepay.sslcommerz.com';

    public function getGatewayCode(): string
    {
        return PaymentGatewaySetting::CODE_SSLCOMMERZ;
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
            throw new RuntimeException('SSLCommerz gateway settings are not configured in the database.');
        }

        return [
            'credentials' => is_array($setting->credentials) ? $setting->credentials : [],
            'is_sandbox' => (bool) $setting->is_sandbox,
        ];
    }

    /**
     * Initiate a hosted payment session with SSLCommerz.
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

            $storeId = (string) ($creds['store_id'] ?? '');
            $storePasswd = (string) ($creds['store_passwd'] ?? '');

            if (empty($storeId) || empty($storePasswd)) {
                return PaymentInitiationResultDto::failed('SSLCommerz configuration missing store_id or store_passwd.');
            }

            $baseUrl = $this->getBaseUrl($sandbox);
            $tranId = 'SSLC_'.Str::upper(Str::random(12));

            $postData = [
                'store_id' => $storeId,
                'store_passwd' => $storePasswd,
                'total_amount' => number_format($amount, 2, '.', ''),
                'currency' => 'BDT',
                'tran_id' => $tranId,
                'success_url' => route('payment.sslcommerz.success'),
                'fail_url' => route('payment.sslcommerz.fail'),
                'cancel_url' => route('payment.sslcommerz.cancel'),
                'ipn_url' => route('payment.sslcommerz.ipn'),

                // Customer details
                'cus_name' => $order->customer_name,
                'cus_email' => $order->customer_email ?? 'customer@example.com',
                'cus_add1' => $order->delivery_address,
                'cus_city' => $order->district?->name_en ?? 'Dhaka',
                'cus_postcode' => '1000',
                'cus_country' => 'Bangladesh',
                'cus_phone' => $order->customer_phone,

                // Shipping details
                'shipping_method' => 'Courier',
                'num_of_item' => 1,

                // Product details
                'product_name' => 'Order '.$order->order_number,
                'product_category' => 'General Trading',
                'product_profile' => 'general',
            ];

            $response = Http::asForm()->post("{$baseUrl}/gwprocess/v4/api.php", $postData);
            $data = $response->json();

            if (! $response->successful() || ($data['status'] ?? '') !== 'SUCCESS' || empty($data['GatewayPageURL'])) {
                $err = $data['failedreason'] ?? ($data['message'] ?? 'Failed to initialize SSLCommerz hosted session.');

                return PaymentInitiationResultDto::failed($err, metadata: $data ?? []);
            }

            $gatewayUrl = (string) $data['GatewayPageURL'];
            $sessionKey = (string) ($data['sessionkey'] ?? '');

            // Persist pending transaction
            $trx = PaymentTransaction::create([
                'order_id' => $order->id,
                'gateway_code' => $this->getGatewayCode(),
                'transaction_id' => $tranId,
                'payment_purpose' => $purpose,
                'amount_bdt' => $amount,
                'gateway_fee_bdt' => 0.0000,
                'currency' => 'BDT',
                'status' => PaymentTransaction::STATUS_PENDING,
                'customer_account' => $order->customer_phone,
                'raw_request_payload' => $postData,
                'raw_response_payload' => $data,
            ]);

            return PaymentInitiationResultDto::successful(
                redirectUrl: $gatewayUrl,
                transactionId: $trx->ulid,
                gatewayPaymentId: $tranId,
                metadata: [
                    'sessionkey' => $sessionKey,
                    'tran_id' => $tranId,
                ],
            );
        } catch (Throwable $e) {
            return PaymentInitiationResultDto::failed('SSLCommerz initiation exception: '.$e->getMessage());
        }
    }

    /**
     * Verify payment status using SSLCommerz Validation API.
     */
    public function verifyPayment(Request $request, ?Order $order = null): PaymentVerificationResultDto
    {
        $status = (string) $request->input('status', '');
        $tranId = (string) $request->input('tran_id', '');
        $valId = (string) $request->input('val_id', '');

        $transaction = PaymentTransaction::where('gateway_code', $this->getGatewayCode())
            ->where('transaction_id', $tranId)
            ->first();

        if ($status !== 'VALID' && $status !== 'VALIDATED' && $status !== 'SUCCESS') {
            $failureReason = match ($status) {
                'FAILED' => 'SSLCommerz transaction was declined or failed.',
                'CANCELLED' => 'SSLCommerz transaction was cancelled by user.',
                default => 'SSLCommerz return status: '.$status,
            };

            if ($transaction) {
                $transaction->update([
                    'status' => $status === 'CANCELLED' ? PaymentTransaction::STATUS_CANCELLED : PaymentTransaction::STATUS_FAILED,
                    'error_message' => $failureReason,
                    'raw_response_payload' => $request->all(),
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: $failureReason,
                transactionId: $transaction?->ulid,
                gatewayTrxId: $tranId,
                rawPayload: $request->all(),
            );
        }

        try {
            $config = $this->resolveSettings();
            $creds = $config['credentials'];
            $sandbox = $config['is_sandbox'];
            $baseUrl = $this->getBaseUrl($sandbox);

            // Call SSLCommerz validation API to ensure the payload was not tampered
            $validateResponse = Http::get("{$baseUrl}/validator/api/validationserverAPI.php", [
                'val_id' => $valId,
                'store_id' => $creds['store_id'] ?? '',
                'store_passwd' => $creds['store_passwd'] ?? '',
                'format' => 'json',
            ]);

            $valData = $validateResponse->json();

            if ($validateResponse->successful() && in_array($valData['status'] ?? '', ['VALID', 'VALIDATED'], true)) {
                $amountPaid = (float) ($valData['amount'] ?? 0.00);
                $cardType = (string) ($valData['card_type'] ?? 'ONLINE');
                $bankTranId = (string) ($valData['bank_tran_id'] ?? $valId);
                $cardNo = (string) ($valData['card_no'] ?? '');

                if ($transaction) {
                    $transaction->update([
                        'status' => PaymentTransaction::STATUS_SUCCESSFUL,
                        'transaction_id' => $bankTranId,
                        'customer_account' => $cardNo !== '' ? $cardNo : $transaction->customer_account,
                        'raw_response_payload' => $valData,
                    ]);
                }

                return PaymentVerificationResultDto::successful(
                    transactionId: $transaction?->ulid ?? $tranId,
                    gatewayTrxId: $bankTranId,
                    amountPaid: $amountPaid,
                    customerAccount: $cardNo,
                    rawPayload: $valData,
                );
            }

            $errMsg = $valData['error'] ?? ($valData['failedreason'] ?? 'SSLCommerz verification failed.');
            if ($transaction) {
                $transaction->update([
                    'status' => PaymentTransaction::STATUS_FAILED,
                    'error_message' => $errMsg,
                    'raw_response_payload' => $valData ?? [],
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: $errMsg,
                transactionId: $transaction?->ulid,
                gatewayTrxId: $tranId,
                rawPayload: $valData ?? [],
            );
        } catch (Throwable $e) {
            if ($transaction) {
                $transaction->update([
                    'status' => PaymentTransaction::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
            }

            return PaymentVerificationResultDto::failed(
                errorMessage: 'Exception during SSLCommerz verification: '.$e->getMessage(),
                transactionId: $transaction?->ulid,
                gatewayTrxId: $tranId,
            );
        }
    }

    /**
     * Pre-flight API test and credential validation.
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

            $storeId = (string) ($effectiveCreds['store_id'] ?? '');
            $storePasswd = (string) ($effectiveCreds['store_passwd'] ?? '');

            if (empty($storeId) || empty($storePasswd)) {
                return GatewayConnectionTestResultDto::failed(
                    gatewayCode: $this->getGatewayCode(),
                    message: 'SSLCommerz test failed: Missing store_id or store_passwd.',
                );
            }

            $baseUrl = $this->getBaseUrl($sandbox);

            // Validation server query test
            $response = Http::get("{$baseUrl}/validator/api/validationserverAPI.php", [
                'val_id' => 'PREFLIGHT_TEST_ID',
                'store_id' => $storeId,
                'store_passwd' => $storePasswd,
                'format' => 'json',
            ]);

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            // An invalid transaction id with valid credentials returns INVALID_TRANSACTION or similar JSON,
            // while bad credentials returns authentication failure error.
            $body = $response->json();
            $status = $body['status'] ?? '';

            if ($status === 'FAILED' && str_contains(strtolower((string) ($body['failedreason'] ?? '')), 'credential')) {
                return GatewayConnectionTestResultDto::failed(
                    gatewayCode: $this->getGatewayCode(),
                    message: 'SSLCommerz authentication failed: Invalid store credentials.',
                    latencyMs: $latencyMs,
                );
            }

            return GatewayConnectionTestResultDto::successful(
                gatewayCode: $this->getGatewayCode(),
                message: 'SSLCommerz API gateway connection verified in '.$latencyMs.'ms.',
                latencyMs: $latencyMs,
                details: [
                    'store_id' => $storeId,
                    'environment' => $sandbox ? 'sandbox' : 'live',
                ],
            );
        } catch (Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return GatewayConnectionTestResultDto::failed(
                gatewayCode: $this->getGatewayCode(),
                message: 'SSLCommerz connection failed: '.$e->getMessage(),
                latencyMs: $latencyMs,
            );
        }
    }
}
