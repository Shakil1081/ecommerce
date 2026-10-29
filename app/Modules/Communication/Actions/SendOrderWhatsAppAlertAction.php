<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\DTOs\WhatsAppDispatchResultDto;
use App\Modules\Communication\Models\WhatsAppSetting;
use App\Modules\Communication\Services\WhatsAppClient;
use App\Modules\Order\Models\Order;

class SendOrderWhatsAppAlertAction
{
    public const EVENT_CONFIRMED = 'order_confirmed';

    public const EVENT_DISPATCHED = 'order_dispatched';

    public const EVENT_DELIVERED = 'order_delivered';

    public function __invoke(
        Order $order,
        string $event = self::EVENT_CONFIRMED,
        array $extraData = [],
    ): WhatsAppDispatchResultDto {
        $setting = WhatsAppSetting::active()->first();

        if (! $setting || ! $setting->isConfigured()) {
            return new WhatsAppDispatchResultDto(
                success: false,
                messageId: null,
                message: 'WhatsApp Business API is not configured or inactive.',
            );
        }

        $client = new WhatsAppClient($setting);
        $phone = $order->customer_phone;

        $components = match ($event) {
            self::EVENT_CONFIRMED => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $order->customer_name],
                        ['type' => 'text', 'text' => $order->order_number],
                        ['type' => 'text', 'text' => '৳'.number_format((float) $order->total_bdt, 2)],
                    ],
                ],
            ],
            self::EVENT_DISPATCHED => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $order->customer_name],
                        ['type' => 'text', 'text' => $order->order_number],
                        ['type' => 'text', 'text' => (string) ($extraData['courier_name'] ?? 'Steadfast Courier')],
                        ['type' => 'text', 'text' => (string) ($extraData['tracking_url'] ?? url("/track-order?ref={$order->order_number}"))],
                    ],
                ],
            ],
            self::EVENT_DELIVERED => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $order->customer_name],
                        ['type' => 'text', 'text' => $order->order_number],
                        ['type' => 'text', 'text' => (string) ($extraData['invoice_url'] ?? url("/orders/{$order->order_number}/invoice"))],
                    ],
                ],
            ],
            default => [],
        };

        $templateName = match ($event) {
            self::EVENT_DISPATCHED => 'order_dispatched_alert',
            self::EVENT_DELIVERED => 'order_delivered_mushak_alert',
            default => 'order_confirmation_alert',
        };

        return $client->sendTemplateMessage(
            to: $phone,
            templateName: $templateName,
            languageCode: 'en_US',
            components: $components,
        );
    }
}
