<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Logistics\Contracts\CourierAdapterContract;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CourierGatewayManager
{
    /**
     * @var array<string, CourierAdapterContract|callable>
     */
    protected array $drivers = [];

    /**
     * Register or override a courier gateway driver.
     */
    public function registerDriver(string $code, CourierAdapterContract|callable $driver): self
    {
        $this->drivers[$code] = $driver;

        return $this;
    }

    /**
     * Resolve the courier adapter for a given courier code.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $code): CourierAdapterContract
    {
        if (isset($this->drivers[$code])) {
            $driver = $this->drivers[$code];
            if (is_callable($driver)) {
                return $driver();
            }

            return $driver;
        }

        // Check if an adapter class exists matching naming convention
        $className = 'App\\Modules\\Logistics\\Adapters\\'.Str::studly($code).'CourierAdapter';
        if (class_exists($className)) {
            $instance = app()->make($className);
            if ($instance instanceof CourierAdapterContract) {
                return $instance;
            }
        }

        throw new InvalidArgumentException("Unsupported or unregistered courier driver: [{$code}]");
    }

    /**
     * Get the default/first active courier adapter.
     */
    public function getActive(): ?CourierAdapterContract
    {
        $setting = CourierGatewaySetting::query()
            ->active()
            ->ordered()
            ->first();

        if (! $setting) {
            return null;
        }

        return $this->resolve($setting->code);
    }

    /**
     * Get all active and sorted courier gateway settings.
     *
     * @return Collection<int, CourierGatewaySetting>
     */
    public function getActiveSettings(): Collection
    {
        return CourierGatewaySetting::query()
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Retrieve the database setting record for a given courier code.
     */
    public function getSetting(string $code): ?CourierGatewaySetting
    {
        return CourierGatewaySetting::where('code', $code)->first();
    }

    /**
     * Check if a courier gateway is active and configured.
     */
    public function isCourierActive(string $code): bool
    {
        $setting = $this->getSetting($code);

        return $setting !== null && $setting->is_active && $setting->isConfigured();
    }

    /**
     * List all supported courier codes.
     *
     * @return list<string>
     */
    public function supportedCodes(): array
    {
        return array_keys(CourierGatewaySetting::SUPPORTED_COURIERS);
    }
}
