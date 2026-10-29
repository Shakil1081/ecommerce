<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Payment\DTOs\GatewayConnectionTestResultDto;
use App\Modules\Payment\Services\PaymentGatewayManager;
use Throwable;

class TestGatewayConnectionAction
{
    public function __construct(
        protected PaymentGatewayManager $manager,
    ) {}

    /**
     * Perform pre-flight API connection verification.
     *
     * @param  array<string, mixed>|null  $credentials
     */
    public function __invoke(
        string $gatewayCode,
        ?array $credentials = null,
        bool $sandbox = true
    ): GatewayConnectionTestResultDto {
        try {
            $setting = $this->manager->getSetting($gatewayCode);

            // Use provided credentials or fallback to existing database credentials
            $effectiveCredentials = $credentials ?? ($setting?->credentials ?? []);

            $adapter = $this->manager->resolve($gatewayCode);
            $result = $adapter->testConnection($effectiveCredentials, $sandbox);

            if ($setting) {
                $setting->update([
                    'last_tested_at' => now(),
                    'last_test_status' => $result->success ? 'successful' : 'failed',
                    'last_test_message' => $result->message,
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $failureResult = GatewayConnectionTestResultDto::failed(
                gatewayCode: $gatewayCode,
                message: 'Connection test failed: '.$e->getMessage(),
            );

            if (isset($setting) && $setting) {
                $setting->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'failed',
                    'last_test_message' => $failureResult->message,
                ]);
            }

            return $failureResult;
        }
    }
}
