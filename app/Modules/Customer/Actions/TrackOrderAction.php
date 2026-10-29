<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\DTOs\OrderTrackingResultDto;
use App\Modules\Logistics\Models\CourierConsignment;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;

final class TrackOrderAction
{
    /**
     * Track an order by order number and customer phone.
     */
    public function __invoke(string $orderNumber, string $phone): ?OrderTrackingResultDto
    {
        $cleanPhone = preg_replace('/\D/', '', $phone) ?? '';
        $last10 = strlen($cleanPhone) >= 10 ? substr($cleanPhone, -10) : $cleanPhone;

        if (empty($orderNumber) || empty($last10)) {
            return null;
        }

        $order = Order::with([
            'items.variant.product',
            'division',
            'district',
            'thana',
            'latestConsignment',
            'statusHistories',
        ])
            ->where(function ($query) use ($orderNumber) {
                $query->where('order_number', trim($orderNumber))
                    ->orWhere('ulid', trim($orderNumber));
            })
            ->where(function ($query) use ($cleanPhone, $last10) {
                $query->where('customer_phone', $cleanPhone)
                    ->orWhere('customer_phone', 'like', '%'.$last10);
            })
            ->first();

        if (! $order) {
            return null;
        }

        return new OrderTrackingResultDto(
            order: $this->formatOrderSummary($order),
            milestones: $this->buildMilestones($order),
            courier: $this->formatCourierInfo($order),
            items: $this->formatItems($order),
            financials: $this->formatFinancials($order),
            shippingAddress: $this->formatShippingAddress($order),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderSummary(Order $order): array
    {
        return [
            'id' => $order->id,
            'ulid' => $order->ulid,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => $this->getStatusLabel($order->status),
            'status_label_bn' => $this->getStatusLabelBn($order->status),
            'status_color' => $this->getStatusColor($order->status),
            'created_at' => $order->created_at?->toIso8601String(),
            'created_at_human' => $order->created_at?->format('d M Y, h:i A'),
            'mushak_6_3_number' => $order->mushak_6_3_number,
        ];
    }

    /**
     * Build the ordered milestone list with completion indicators.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMilestones(Order $order): array
    {
        $statusRanks = [
            Order::STATUS_PENDING => 1,
            Order::STATUS_CONFIRMED => 2,
            Order::STATUS_PROCESSING => 3,
            Order::STATUS_PICKED_AND_PACKED => 3,
            Order::STATUS_HANDED_TO_COURIER => 4,
            Order::STATUS_OUT_FOR_DELIVERY => 5,
            Order::STATUS_DELIVERED => 6,
        ];

        $currentRank = $statusRanks[$order->status] ?? 1;
        $isCancelled = $order->status === Order::STATUS_CANCELLED;
        $isReturned = $order->status === Order::STATUS_RETURNED;

        // Extract milestone dates from order fields or histories
        $confirmedAt = $order->confirmed_at?->format('d M Y, h:i A');
        $packedAt = $this->findHistoryDate($order, [Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED]);
        $shippedAt = $order->shipped_at?->format('d M Y, h:i A');
        $deliveredAt = $order->delivered_at?->format('d M Y, h:i A');

        $milestones = [
            [
                'step' => 1,
                'key' => 'placed',
                'title' => 'Order Placed',
                'title_bn' => 'অর্ডার গ্রহণ করা হয়েছে',
                'description' => 'We have received your order successfully.',
                'description_bn' => 'আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।',
                'timestamp' => $order->created_at?->format('d M Y, h:i A'),
                'is_completed' => true,
                'is_current' => $order->status === Order::STATUS_PENDING && ! $isCancelled,
            ],
            [
                'step' => 2,
                'key' => 'confirmed',
                'title' => 'Order Confirmed',
                'title_bn' => 'অর্ডার নিশ্চিত করা হয়েছে',
                'description' => 'Order details verified and approved.',
                'description_bn' => 'অর্ডারের বিবরণ যাচাই ও নিশ্চিত করা হয়েছে।',
                'timestamp' => $confirmedAt,
                'is_completed' => $currentRank >= 2 && ! $isCancelled,
                'is_current' => $order->status === Order::STATUS_CONFIRMED && ! $isCancelled,
            ],
            [
                'step' => 3,
                'key' => 'processing',
                'title' => 'Picked & Packed',
                'title_bn' => 'প্যাকিং সম্পন্ন',
                'description' => 'Items retrieved from warehouse and packed securely.',
                'description_bn' => 'ওয়্যারহাউস থেকে পণ্য সংগ্রহ ও সুরক্ষিতভাবে প্যাক করা হয়েছে।',
                'timestamp' => $packedAt,
                'is_completed' => $currentRank >= 3 && ! $isCancelled,
                'is_current' => in_array($order->status, [Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED], true) && ! $isCancelled,
            ],
            [
                'step' => 4,
                'key' => 'handed_to_courier',
                'title' => 'Handed to Courier',
                'title_bn' => 'কুরিয়ারে হস্তান্তর',
                'description' => 'Package handed over to our delivery partner.',
                'description_bn' => 'পার্সেলটি আমাদের ডেলিভারি পার্টনারের নিকট হস্তান্তর করা হয়েছে।',
                'timestamp' => $shippedAt,
                'is_completed' => $currentRank >= 4 && ! $isCancelled,
                'is_current' => $order->status === Order::STATUS_HANDED_TO_COURIER && ! $isCancelled,
            ],
            [
                'step' => 5,
                'key' => 'out_for_delivery',
                'title' => 'Out for Delivery',
                'title_bn' => 'ডেলিভারির পথে',
                'description' => 'Delivery rider is on the way to your doorstep.',
                'description_bn' => 'ডেলিভারি রাইডার আপনার ঠিকানার উদ্দেশ্যে বের হয়েছেন।',
                'timestamp' => null,
                'is_completed' => $currentRank >= 5 && ! $isCancelled,
                'is_current' => $order->status === Order::STATUS_OUT_FOR_DELIVERY && ! $isCancelled,
            ],
            [
                'step' => 6,
                'key' => 'delivered',
                'title' => 'Delivered',
                'title_bn' => 'ডেলিভারি সম্পন্ন',
                'description' => 'Package delivered to recipient successfully.',
                'description_bn' => 'পার্সেলটি সফলভাবে গ্রাহকের কাছে পৌঁছে দেওয়া হয়েছে।',
                'timestamp' => $deliveredAt,
                'is_completed' => $currentRank >= 6 && ! $isCancelled,
                'is_current' => $order->status === Order::STATUS_DELIVERED && ! $isCancelled,
            ],
        ];

        if ($isCancelled) {
            $cancelledAt = $order->cancelled_at?->format('d M Y, h:i A') ?? $order->updated_at?->format('d M Y, h:i A');
            $milestones[] = [
                'step' => 7,
                'key' => 'cancelled',
                'title' => 'Order Cancelled',
                'title_bn' => 'অর্ডার বাতিল করা হয়েছে',
                'description' => 'This order was cancelled.',
                'description_bn' => 'এই অর্ডারটি বাতিল করা হয়েছে।',
                'timestamp' => $cancelledAt,
                'is_completed' => true,
                'is_current' => true,
            ];
        } elseif ($isReturned) {
            $milestones[] = [
                'step' => 7,
                'key' => 'returned',
                'title' => 'Returned to Hub',
                'title_bn' => 'পার্সেল ফেরত এসেছে',
                'description' => 'Package was returned by customer or courier.',
                'description_bn' => 'পার্সেলটি ওয়্যারহাউসে ফেরত এসেছে।',
                'timestamp' => $order->updated_at?->format('d M Y, h:i A'),
                'is_completed' => true,
                'is_current' => true,
            ];
        }

        return $milestones;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatCourierInfo(Order $order): ?array
    {
        /** @var CourierConsignment|null $consignment */
        $consignment = $order->latestConsignment;

        $courierCode = $consignment?->courier_code ?? $order->courier_code;
        $trackingCode = $consignment?->tracking_code ?? $order->courier_tracking_code;
        $consignmentId = $consignment?->consignment_id ?? $order->courier_consignment_id;

        if (! $courierCode && ! $trackingCode) {
            return null;
        }

        $courierNames = [
            'steadfast' => 'Steadfast Courier',
            'pathao' => 'Pathao Courier',
            'redx' => 'RedX Delivery',
            'paperfly' => 'Paperfly Delivery',
            'sundarban' => 'Sundarban Courier Service',
        ];

        return [
            'courier_code' => $courierCode,
            'courier_name' => $courierNames[strtolower((string) $courierCode)] ?? ucfirst((string) $courierCode),
            'tracking_code' => $trackingCode,
            'consignment_id' => $consignmentId,
            'status' => $consignment?->status ?? 'in_transit',
            'status_label' => ucfirst(str_replace('_', ' ', (string) ($consignment?->status ?? 'in_transit'))),
            'tracking_url' => $this->resolveTrackingUrl($courierCode, $trackingCode, $consignmentId),
            'dispatched_at' => $consignment?->dispatched_at?->format('d M Y, h:i A'),
        ];
    }

    /**
     * Resolve the external courier tracking link.
     */
    private function resolveTrackingUrl(?string $courierCode, ?string $trackingCode, ?string $consignmentId): ?string
    {
        if (! $courierCode) {
            return null;
        }

        return match (strtolower($courierCode)) {
            'steadfast' => $trackingCode ? "https://steadfast.com.bd/t/{$trackingCode}" : null,
            'pathao' => $consignmentId ? "https://merchant.pathao.com/tracking?consignment_id={$consignmentId}" : null,
            'redx' => $trackingCode ? "https://redx.com.bd/track/{$trackingCode}" : null,
            'paperfly' => $trackingCode ? "https://paperfly.com.bd/tracking?order_id={$trackingCode}" : null,
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatItems(Order $order): array
    {
        $items = [];

        foreach ($order->items as $item) {
            $product = $item->variant?->product;
            $items[] = [
                'id' => $item->id,
                'product_title' => $item->product_name ?? $product?->name ?? 'Product',
                'variant_sku' => $item->sku ?? $item->variant?->sku ?? 'N/A',
                'attributes' => $item->attributes ?? $item->variant?->attributes ?? [],
                'quantity' => $item->quantity,
                'unit_price_bdt' => number_format((float) $item->unit_price_bdt, 2, '.', ''),
                'total_price_bdt' => number_format((float) ($item->subtotal_bdt ?? ((float) $item->unit_price_bdt * $item->quantity)), 2, '.', ''),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFinancials(Order $order): array
    {
        return [
            'subtotal_bdt' => number_format((float) $order->subtotal_bdt, 2, '.', ''),
            'discount_amount_bdt' => number_format((float) $order->discount_amount_bdt, 2, '.', ''),
            'shipping_fee_bdt' => number_format((float) $order->shipping_fee_bdt, 2, '.', ''),
            'total_bdt' => number_format((float) $order->total_bdt, 2, '.', ''),
            'advance_paid_bdt' => number_format((float) $order->advance_paid_bdt, 2, '.', ''),
            'payable_cod_amount_bdt' => number_format((float) $order->payable_cod_amount_bdt, 2, '.', ''),
            'payment_method' => $order->payment_method,
            'payment_method_label' => $this->getPaymentMethodLabel($order->payment_method),
            'payment_status' => $order->payment_status,
            'coupon_code' => $order->coupon_code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatShippingAddress(Order $order): array
    {
        return [
            'customer_name' => $order->customer_name,
            'customer_phone_masked' => $this->maskPhoneNumber($order->customer_phone),
            'delivery_zone' => $order->delivery_zone,
            'delivery_address' => $order->delivery_address,
            'division' => $order->division?->name_en,
            'division_bn' => $order->division?->name_bn,
            'district' => $order->district?->name_en,
            'district_bn' => $order->district?->name_bn,
            'thana' => $order->thana?->name_en,
            'thana_bn' => $order->thana?->name_bn,
        ];
    }

    /**
     * Mask customer phone number e.g. 017****5678.
     */
    private function maskPhoneNumber(string $phone): string
    {
        $clean = preg_replace('/\D/', '', $phone) ?? '';
        $length = strlen($clean);

        if ($length < 7) {
            return $phone;
        }

        $start = substr($clean, 0, 3);
        $end = substr($clean, -4);

        return $start.str_repeat('*', max(4, $length - 7)).$end;
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function findHistoryDate(Order $order, array $statuses): ?string
    {
        /** @var OrderStatusHistory|null $history */
        $history = $order->statusHistories
            ->whereIn('to_status', $statuses)
            ->first();

        return $history?->created_at?->format('d M Y, h:i A');
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => 'Pending Verification',
            Order::STATUS_CONFIRMED => 'Order Confirmed',
            Order::STATUS_PROCESSING => 'Processing',
            Order::STATUS_PICKED_AND_PACKED => 'Picked & Packed',
            Order::STATUS_HANDED_TO_COURIER => 'Handed to Courier',
            Order::STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
            Order::STATUS_DELIVERED => 'Delivered',
            Order::STATUS_RETURNED => 'Returned',
            Order::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function getStatusLabelBn(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => 'যাচাই প্রক্রিয়াধীন',
            Order::STATUS_CONFIRMED => 'অর্ডার নিশ্চিত করা হয়েছে',
            Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED => 'প্যাকেজিং সম্পন্ন',
            Order::STATUS_HANDED_TO_COURIER => 'কুরিয়ারে হস্তান্তর',
            Order::STATUS_OUT_FOR_DELIVERY => 'ডেলিভারির পথে',
            Order::STATUS_DELIVERED => 'ডেলিভারি সম্পন্ন',
            Order::STATUS_RETURNED => 'ফেরত এসেছে',
            Order::STATUS_CANCELLED => 'বাতিল করা হয়েছে',
            default => $status,
        };
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => 'warning',
            Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_PICKED_AND_PACKED => 'info',
            Order::STATUS_HANDED_TO_COURIER, Order::STATUS_OUT_FOR_DELIVERY => 'primary',
            Order::STATUS_DELIVERED => 'success',
            Order::STATUS_RETURNED, Order::STATUS_CANCELLED => 'danger',
            default => 'secondary',
        };
    }

    private function getPaymentMethodLabel(string $method): string
    {
        return match (strtolower($method)) {
            'cash_on_delivery', 'cod' => 'Cash on Delivery (ক্যাশ অন ডেলিভারি)',
            'bkash' => 'bKash Online Payment',
            'nagad' => 'Nagad Online Payment',
            'sslcommerz' => 'SSLCommerz Gateway',
            'bank_transfer' => 'Direct Bank Transfer',
            default => strtoupper($method),
        };
    }
}
