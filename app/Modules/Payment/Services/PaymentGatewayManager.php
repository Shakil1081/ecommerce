<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Adapters\BankTransferPaymentAdapter;
use App\Modules\Payment\Adapters\CodPaymentAdapter;
use App\Modules\Payment\Contracts\PaymentGatewayContract;
use App\Modules\Payment\Models\PaymentGatewaySetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGatewayContract|callable>
     */
    protected array $drivers = [];

    public function __construct()
    {
        $this->registerDefaultDrivers();
    }

    /**
     * Register core internal adapters.
     */
    protected function registerDefaultDrivers(): void
    {
        $this->registerDriver(PaymentGatewaySetting::CODE_COD, new CodPaymentAdapter);
        $this->registerDriver(PaymentGatewaySetting::CODE_BANK_TRANSFER, new BankTransferPaymentAdapter);
    }

    /**
     * Register or override a payment gateway driver.
     */
    public function registerDriver(string $code, PaymentGatewayContract|callable $driver): self
    {
        $this->drivers[$code] = $driver;

        return $this;
    }

    /**
     * Resolve the gateway adapter for a given gateway code.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $code): PaymentGatewayContract
    {
        if (isset($this->drivers[$code])) {
            $driver = $this->drivers[$code];
            if (is_callable($driver)) {
                return $driver();
            }

            return $driver;
        }

        // Check if an adapter class exists matching naming convention
        $className = 'App\\Modules\\Payment\\Adapters\\'.Str::studly($code).'PaymentAdapter';
        if (class_exists($className)) {
            $instance = app()->make($className);
            if ($instance instanceof PaymentGatewayContract) {
                return $instance;
            }
        }

        throw new InvalidArgumentException("Unsupported or unregistered payment gateway driver: [{$code}]");
    }

    /**
     * Get all active and sorted gateway settings for storefront checkout.
     *
     * @return Collection<int, PaymentGatewaySetting>
     */
    public function getActiveGateways(): Collection
    {
        return PaymentGatewaySetting::query()
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Retrieve the database setting record for a given gateway code.
     */
    public function getSetting(string $code): ?PaymentGatewaySetting
    {
        return PaymentGatewaySetting::where('code', $code)->first();
    }

    /**
     * Check if a gateway is configured and active.
     */
    public function isGatewayActive(string $code): bool
    {
        $setting = $this->getSetting($code);

        return $setting !== null && $setting->is_active && $setting->isConfigured();
    }
}
