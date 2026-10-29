<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\DTOs\CustomerFraudListCheckResultDto;
use App\Modules\Customer\Models\CustomerFraudList;

final class CheckCustomerFraudListAction
{
    /**
     * Check if a customer matches either the Merchant Blacklist or Whitelist.
     */
    public function __invoke(
        ?string $phone = null,
        ?string $ipAddress = null,
        ?string $deviceFingerprint = null
    ): CustomerFraudListCheckResultDto {
        if (empty($phone) && empty($ipAddress) && empty($deviceFingerprint)) {
            return new CustomerFraudListCheckResultDto(
                isBlacklisted: false,
                isWhitelisted: false
            );
        }

        // 1. Check Whitelist first: VIP / trusted customers who bypass friction
        /** @var CustomerFraudList|null $whitelistEntry */
        $whitelistEntry = CustomerFraudList::query()
            ->whitelist()
            ->matchIdentifier($phone, $ipAddress, $deviceFingerprint)
            ->first();

        if ($whitelistEntry !== null) {
            return new CustomerFraudListCheckResultDto(
                isBlacklisted: false,
                isWhitelisted: true,
                matchedWhitelistEntry: $whitelistEntry,
                reason: $whitelistEntry->reason ?? 'Customer is on the merchant VIP whitelist.'
            );
        }

        // 2. Check Blacklist: blocked phone numbers, abusive IPs, or repeat returned devices
        /** @var CustomerFraudList|null $blacklistEntry */
        $blacklistEntry = CustomerFraudList::query()
            ->blacklist()
            ->matchIdentifier($phone, $ipAddress, $deviceFingerprint)
            ->first();

        if ($blacklistEntry !== null) {
            return new CustomerFraudListCheckResultDto(
                isBlacklisted: true,
                isWhitelisted: false,
                matchedBlacklistEntry: $blacklistEntry,
                reason: $blacklistEntry->reason ?? 'Customer identifier is on the merchant fraud blacklist.'
            );
        }

        return new CustomerFraudListCheckResultDto(
            isBlacklisted: false,
            isWhitelisted: false
        );
    }
}
