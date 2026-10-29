<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Adapters\SteadfastCourierAdapter;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Logistics\Models\CourierGatewaySetting;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SteadfastWebhookController extends Controller
{
    public function __invoke(Request $request, SteadfastCourierAdapter $adapter): JsonResponse
    {
        $setting = CourierGatewaySetting::where('code', CourierGatewaySetting::CODE_STEADFAST)->first();

        // Optional webhook secret verification if configured
        if ($setting && ! empty($setting->webhook_secret)) {
            $headerToken = $request->header('X-Webhook-Secret') ?? $request->query('secret');
            if ($headerToken !== $setting->webhook_secret) {
                Log::warning('Steadfast webhook rejected: invalid secret token');

                return response()->json(['status' => 'error', 'message' => 'Unauthorized webhook secret'], 401);
            }
        }

        $payload = $request->all();
        $consignmentId = (string) ($payload['consignment_id'] ?? $payload['order_id'] ?? '');
        $invoice = (string) ($payload['invoice'] ?? '');
        $trackingCode = (string) ($payload['tracking_code'] ?? '');
        $rawStatus = (string) ($payload['status'] ?? $payload['delivery_status'] ?? '');

        if (empty($consignmentId) && empty($invoice) && empty($trackingCode)) {
            return response()->json(['status' => 'error', 'message' => 'Missing consignment identifier in payload'], 422);
        }

        // Locate consignment by ID, tracking code, or invoice
        $consignment = CourierConsignment::query()
            ->when($consignmentId !== '', fn ($q) => $q->where('consignment_id', $consignmentId))
            ->when($trackingCode !== '', fn ($q) => $q->orWhere('tracking_code', $trackingCode))
            ->first();

        if (! $consignment && $invoice !== '') {
            $order = Order::where('order_number', $invoice)->first();
            $consignment = $order?->consignments()->first();
        }

        if (! $consignment) {
            Log::warning('Steadfast webhook received for unknown consignment', $payload);

            return response()->json(['status' => 'error', 'message' => 'Consignment not found'], 404);
        }

        $mappedStatus = $adapter->mapDeliveryStatus($rawStatus);
        $order = $consignment->order;

        $consignmentUpdates = [
            'status' => $mappedStatus,
            'last_webhook_payload' => $payload,
        ];

        if (isset($payload['cod_amount'])) {
            $consignmentUpdates['cod_amount_bdt'] = (string) $payload['cod_amount'];
        }

        if ($mappedStatus === CourierConsignment::STATUS_DELIVERED) {
            $consignmentUpdates['delivered_at'] = now();
        } elseif ($mappedStatus === CourierConsignment::STATUS_RETURNED || $mappedStatus === CourierConsignment::STATUS_CANCELLED) {
            $consignmentUpdates['returned_at'] = now();
        }

        $consignment->update($consignmentUpdates);

        // Update Order lifecycle
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
                        'notes' => "Courier status update: Steadfast reported '{$rawStatus}'",
                        'created_at' => now(),
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Steadfast consignment updated successfully',
            'consignment_id' => $consignment->consignment_id,
            'status_mapped' => $mappedStatus,
        ]);
    }
}
