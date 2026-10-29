<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Adapters\PathaoCourierAdapter;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PathaoWebhookController extends Controller
{
    public function __invoke(Request $request, PathaoCourierAdapter $adapter): JsonResponse
    {
        $setting = CourierGatewaySetting::where('code', CourierGatewaySetting::CODE_PATHAO)->first();

        // Optional webhook signature/secret verification
        if ($setting && ! empty($setting->webhook_secret)) {
            $incomingSecret = $request->header('X-Webhook-Secret')
                ?? $request->header('X-Pathao-Signature')
                ?? $request->query('secret');

            if ($incomingSecret !== $setting->webhook_secret) {
                Log::warning('Pathao webhook rejected: invalid secret');

                return response()->json(['status' => 'error', 'message' => 'Unauthorized webhook secret'], 401);
            }
        }

        $payload = $request->all();
        $consignmentId = (string) ($payload['consignment_id'] ?? '');
        $merchantOrderId = (string) ($payload['merchant_order_id'] ?? '');
        $rawStatus = (string) ($payload['order_status'] ?? $payload['order_status_slug'] ?? $payload['status'] ?? '');

        if ($consignmentId === '' && $merchantOrderId === '') {
            return response()->json(['status' => 'error', 'message' => 'Missing consignment identifier in payload'], 422);
        }

        $consignment = CourierConsignment::query()
            ->when($consignmentId !== '', fn ($q) => $q->where('consignment_id', $consignmentId))
            ->first();

        if (! $consignment && $merchantOrderId !== '') {
            $order = Order::where('order_number', $merchantOrderId)->first();
            $consignment = $order?->consignments()->first();
        }

        if (! $consignment) {
            Log::warning('Pathao webhook received for unknown consignment', $payload);

            return response()->json(['status' => 'error', 'message' => 'Consignment not found'], 404);
        }

        $mappedStatus = $adapter->mapDeliveryStatus($rawStatus);
        $order = $consignment->order;

        $consignmentUpdates = [
            'status' => $mappedStatus,
            'last_webhook_payload' => $payload,
        ];

        if (isset($payload['collected_amount'])) {
            $consignmentUpdates['cod_amount_bdt'] = (string) $payload['collected_amount'];
        }

        if (isset($payload['delivery_fee'])) {
            $consignmentUpdates['delivery_fee_bdt'] = (string) $payload['delivery_fee'];
        }

        if ($mappedStatus === CourierConsignment::STATUS_DELIVERED) {
            $consignmentUpdates['delivered_at'] = now();
        } elseif ($mappedStatus === CourierConsignment::STATUS_RETURNED || $mappedStatus === CourierConsignment::STATUS_CANCELLED) {
            $consignmentUpdates['returned_at'] = now();
        }

        $consignment->update($consignmentUpdates);

        // Update Order state
        if ($order) {
            $previousStatus = $order->status;
            $orderUpdates = [];

            if ($mappedStatus === CourierConsignment::STATUS_DELIVERED) {
                $orderUpdates['status'] = Order::STATUS_DELIVERED;
                $orderUpdates['payment_status'] = Order::PAYMENT_PAID;
                $orderUpdates['payable_cod_amount_bdt'] = '0.0000';
                $orderUpdates['delivered_at'] = now();
            } elseif ($mappedStatus === CourierConsignment::STATUS_PARTIAL_DELIVERED) {
                $orderUpdates['status'] = Order::STATUS_DELIVERED;
                $orderUpdates['delivered_at'] = now();
            } elseif ($mappedStatus === CourierConsignment::STATUS_RETURNED || $mappedStatus === CourierConsignment::STATUS_CANCELLED) {
                $orderUpdates['status'] = Order::STATUS_RETURNED;
                $orderUpdates['cancelled_at'] = now();
            } elseif ($mappedStatus === CourierConsignment::STATUS_OUT_FOR_DELIVERY) {
                $orderUpdates['status'] = Order::STATUS_OUT_FOR_DELIVERY;
            } elseif ($mappedStatus === CourierConsignment::STATUS_IN_TRANSIT) {
                $orderUpdates['status'] = Order::STATUS_HANDED_TO_COURIER;
            }

            if (! empty($orderUpdates)) {
                $order->update($orderUpdates);

                if (isset($orderUpdates['status']) && $orderUpdates['status'] !== $previousStatus) {
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'from_status' => $previousStatus,
                        'to_status' => $orderUpdates['status'],
                        'notes' => "Courier status update: Pathao reported '{$rawStatus}'",
                        'created_at' => now(),
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pathao consignment updated successfully',
            'consignment_id' => $consignment->consignment_id,
            'status_mapped' => $mappedStatus,
        ]);
    }
}
