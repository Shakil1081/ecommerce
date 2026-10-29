<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Adapters\GreenwebSmsAdapter;
use App\Modules\Communication\Adapters\ReveSmsAdapter;
use App\Modules\Communication\Adapters\TwilioSmsAdapter;
use App\Modules\Communication\Contracts\SmsGatewayContract;
use App\Modules\Communication\Models\SmsGatewaySetting;
use InvalidArgumentException;

class SmsGatewayManager
{
    /**
     * Alias for resolve().
     *
     * @param  array<string, mixed>|null  $overrideCredentials
     */
    public function driver(?string $code = null, ?array $overrideCredentials = null): SmsGatewayContract
    {
        return $this->resolve($code, $overrideCredentials);
    }

    /**
     * Resolve an SMS gateway driver instance.
     *
     * @param  array<string, mixed>|null  $overrideCredentials
     */
    public function resolve(?string $code = null, ?array $overrideCredentials = null): SmsGatewayContract
    {
        $setting = null;

        if ($code !== null) {
            $setting = SmsGatewaySetting::where('code', $code)->first();
        } else {
            $setting = SmsGatewaySetting::active()->ordered()->first();
            $code = $setting?->code;
        }

        return match ($code) {
            SmsGatewaySetting::CODE_GREENWEB => new GreenwebSmsAdapter($setting, $overrideCredentials),
            SmsGatewaySetting::CODE_REVE => new ReveSmsAdapter($setting, $overrideCredentials),
            SmsGatewaySetting::CODE_TWILIO => new TwilioSmsAdapter($setting, $overrideCredentials),
            default => throw new InvalidArgumentException("Unsupported SMS gateway code: [{$code}]."),
        };
    }

    /**
     * Retrieve the currently active configured SMS gateway adapter.
     */
    public function getActiveGateway(): ?SmsGatewayContract
    {
        $activeSetting = SmsGatewaySetting::active()->ordered()->first();

        if (! $activeSetting || ! $activeSetting->isConfigured()) {
            return null;
        }

        return $this->resolve($activeSetting->code);
    }
}
