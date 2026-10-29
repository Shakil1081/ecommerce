<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\CustomerFraudList;
use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use Illuminate\Validation\ValidationException;

final class AddCustomerToFraudListAction
{
    /**
     * Add a record to the merchant blacklist or whitelist.
     *
     * @param  array{
     *     type: string,
     *     phone_number?: ?string,
     *     ip_address?: ?string,
     *     device_fingerprint?: ?string,
     *     customer_name?: ?string,
     *     reason?: ?string,
     *     blocked_by_user_id?: ?int
     * }  $data
     *
     * @throws ValidationException
     */
    public function __invoke(array $data): CustomerFraudList
    {
        $phone = ! empty($data['phone_number']) ? BangladeshPhoneNumberRule::toLocalFormat($data['phone_number']) : null;
        $ip = ! empty($data['ip_address']) ? trim($data['ip_address']) : null;
        $device = ! empty($data['device_fingerprint']) ? trim($data['device_fingerprint']) : null;

        if (empty($phone) && empty($ip) && empty($device)) {
            throw ValidationException::withMessages([
                'identifier' => ['At least one identifier (phone number, IP address, or device fingerprint) must be provided.'],
            ]);
        }

        $type = in_array($data['type'], [CustomerFraudList::TYPE_BLACKLIST, CustomerFraudList::TYPE_WHITELIST], true)
            ? $data['type']
            : CustomerFraudList::TYPE_BLACKLIST;

        /** @var CustomerFraudList $record */
        $record = CustomerFraudList::create([
            'type' => $type,
            'phone_number' => $phone,
            'ip_address' => $ip,
            'device_fingerprint' => $device,
            'customer_name' => $data['customer_name'] ?? null,
            'reason' => $data['reason'] ?? null,
            'blocked_by_user_id' => $data['blocked_by_user_id'] ?? null,
        ]);

        return $record;
    }
}
