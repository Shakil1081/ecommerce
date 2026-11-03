<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Logistics\DTOs\CourierConnectionTestResultDto;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Logistics\Services\CourierGatewayManager;
use Throwable;

class TestCourierConnectionAction
{
    public function __construct(
        protected CourierGatewayManager $manager,
    ) {}

    /**
     * Perform pre-flight API connection verification for a courier gateway.
     *
     * @param  array<string, mixed>|null  $credentials
     */
    public function __invoke(
        CourierGatewaySetting|string $courier,
        ?array $credentials = null,
        ?bool $sandbox = null,
        ?string $baseUrl = null,
    ): CourierConnectionTestResultDto {
        $setting = null;

        if ($courier instanceof CourierGatewaySetting) {
            $setting = $courier;
            $code = $setting->code;
            $credentials ??= $setting->credentials;
            $sandbox ??= $setting->is_sandbox;
            $baseUrl ??= $setting->base_url;
        } else {
            $code = $courier;
            $setting = $this->manager->getSetting($code);
            $credentials ??= $setting?->credentials;
            $sandbox ??= $setting ? $setting->is_sandbox : false;
            $baseUrl ??= $setting?->base_url;
        }

        try {
            // Create a transient or cloned setting instance if needed
            $testSetting = $setting ? clone $setting : new CourierGatewaySetting([
                'code' => $code,
                'name' => CourierGatewaySetting::SUPPORTED_COURIERS[$code] ?? ucfirst($code),
                'is_sandbox' => (bool) $sandbox,
                'credentials' => $credentials,
                'base_url' => ! empty($baseUrl) ? $baseUrl : null,
            ]);

            if ($credentials !== null) {
                $testSetting->credentials = $credentials;
            }
            $testSetting->base_url = ! empty($baseUrl) ? $baseUrl : null;
            $testSetting->is_sandbox = (bool) $sandbox;

            $adapter = $this->manager->resolve($code);
            $result = $adapter->testConnection($testSetting);

            if ($setting && $setting->exists) {
                $setting->update([
                    'last_tested_at' => now(),
                    'last_test_status' => $result->success ? 'success' : 'failed',
                    'last_test_message' => $result->message,
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $failureResult = CourierConnectionTestResultDto::failed(
                message: 'Connection test failed: '.$e->getMessage(),
            );

            if ($setting && $setting->exists) {
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
