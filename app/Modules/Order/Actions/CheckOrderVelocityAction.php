<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use App\Modules\Order\DTOs\OrderVelocityResultDto;
use App\Modules\Order\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class CheckOrderVelocityAction
{
    /**
     * Unfulfilled order statuses that represent in-flight, uncompleted orders.
     */
    public const UNFULFILLED_STATUSES = [
        Order::STATUS_PENDING,
        Order::STATUS_CONFIRMED,
        Order::STATUS_PROCESSING,
        Order::STATUS_PICKED_AND_PACKED,
        Order::STATUS_HANDED_TO_COURIER,
        Order::STATUS_OUT_FOR_DELIVERY,
    ];

    /**
     * Evaluate order velocity constraints for customer phone, IP address, and device fingerprint.
     */
    public function __invoke(
        string $phone,
        ?string $ipAddress = null,
        ?string $deviceFingerprint = null,
        string $paymentMethod = 'cash_on_delivery',
        int $maxDailyOrdersPerIp = 2,
        int $maxDailyOrdersPerDevice = 2,
        int $maxActiveCodOrders = 1,
        int $timeWindowHours = 24
    ): OrderVelocityResultDto {
        $violations = [];
        $primaryReason = null;
        $since = Carbon::now()->subHours($timeWindowHours);

        // 1. Check active unfulfilled Cash on Delivery (COD) orders for this phone number
        $localPhone = BangladeshPhoneNumberRule::toLocalFormat($phone);
        $e164Phone = '+88'.$localPhone;

        $activeCodOrdersCount = Order::query()
            ->where(function (Builder $query) use ($phone, $localPhone, $e164Phone) {
                $query->where('customer_phone', $phone)
                    ->orWhere('customer_phone', $localPhone)
                    ->orWhere('customer_phone', $e164Phone);
            })
            ->where('payment_method', 'cash_on_delivery')
            ->whereIn('status', self::UNFULFILLED_STATUSES)
            ->count();

        if ($paymentMethod === 'cash_on_delivery' && $activeCodOrdersCount >= $maxActiveCodOrders) {
            $violations[] = 'active_cod_limit_exceeded';
            $primaryReason ??= "Customer already has {$activeCodOrdersCount} active unfulfilled Cash on Delivery order(s). Maximum permitted is {$maxActiveCodOrders}.";
        }

        // 2. Check daily order burst limit for the client IP address
        $dailyOrdersFromIpCount = 0;
        if (! empty($ipAddress)) {
            $dailyOrdersFromIpCount = Order::query()
                ->where('ip_address', $ipAddress)
                ->where('created_at', '>=', $since)
                ->whereNotIn('status', [Order::STATUS_CANCELLED])
                ->count();

            if ($dailyOrdersFromIpCount >= $maxDailyOrdersPerIp) {
                $violations[] = 'ip_velocity_exceeded';
                $primaryReason ??= "Exceeded maximum of {$maxDailyOrdersPerIp} orders per {$timeWindowHours} hours from IP address [{$ipAddress}].";
            }
        }

        // 3. Check daily order burst limit for the device fingerprint
        $dailyOrdersFromDeviceCount = 0;
        if (! empty($deviceFingerprint)) {
            $dailyOrdersFromDeviceCount = Order::query()
                ->where('device_fingerprint', $deviceFingerprint)
                ->where('created_at', '>=', $since)
                ->whereNotIn('status', [Order::STATUS_CANCELLED])
                ->count();

            if ($dailyOrdersFromDeviceCount >= $maxDailyOrdersPerDevice) {
                $violations[] = 'device_velocity_exceeded';
                $primaryReason ??= "Exceeded maximum of {$maxDailyOrdersPerDevice} orders per {$timeWindowHours} hours from this device.";
            }
        }

        $isAllowed = empty($violations);

        return new OrderVelocityResultDto(
            isAllowed: $isAllowed,
            activeCodOrdersCount: $activeCodOrdersCount,
            dailyOrdersFromIpCount: $dailyOrdersFromIpCount,
            dailyOrdersFromDeviceCount: $dailyOrdersFromDeviceCount,
            violations: $violations,
            primaryReason: $primaryReason
        );
    }
}
