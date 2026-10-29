<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\VoiceCallingContract;
use App\Modules\Communication\Models\VoiceCallingSetting;
use InvalidArgumentException;

class VoiceCallingManager
{
    /**
     * Resolve a voice calling adapter instance.
     *
     * @param  array<string, mixed>|null  $overrideCredentials
     */
    public function resolve(?string $provider = null, ?array $overrideCredentials = null): VoiceCallingContract
    {
        $setting = null;

        if ($provider !== null) {
            $setting = VoiceCallingSetting::where('provider', $provider)->first();
        } else {
            $setting = VoiceCallingSetting::active()->first();
            $provider = $setting?->provider ?? VoiceCallingSetting::PROVIDER_TWILIO;
        }

        return match ($provider) {
            VoiceCallingSetting::PROVIDER_TWILIO => new TwilioVoiceAdapter($setting, $overrideCredentials),
            default => throw new InvalidArgumentException("Unsupported voice calling provider: [{$provider}]."),
        };
    }

    /**
     * Retrieve the currently active configured voice calling driver.
     */
    public function getActiveDriver(): ?VoiceCallingContract
    {
        $setting = VoiceCallingSetting::active()->first();

        if (! $setting || ! $setting->isConfigured()) {
            return null;
        }

        return $this->resolve($setting->provider);
    }
}
