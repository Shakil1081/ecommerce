<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Actions\BookCourierConsignmentAction;
use App\Modules\Logistics\Actions\BulkBookCourierConsignmentsAction;
use App\Modules\Logistics\Actions\GenerateShippingLabelHtmlAction;
use App\Modules\Order\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShippingLabelController extends Controller
{
    /**
     * Render 4x6 thermal printable shipping label HTML for a single order.
     */
    public function show(Order $order, GenerateShippingLabelHtmlAction $action): Response
    {
        $html = $action($order);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Render concatenated 4x6 thermal printable shipping labels for multiple orders.
     */
    public function bulk(Request $request, GenerateShippingLabelHtmlAction $action): Response
    {
        $orderIds = (array) $request->input('order_ids', []);
        $orders = Order::whereIn('id', $orderIds)->get()->all();

        $html = $action($orders);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Single order consignment booking endpoint.
     */
    public function book(
        Request $request,
        Order $order,
        BookCourierConsignmentAction $action
    ): JsonResponse {
        $courierCode = $request->input('courier_code') ? (string) $request->input('courier_code') : null;
        $options = (array) $request->input('options', []);

        $result = $action($order, $courierCode, $options);

        if ($result->success) {
            return response()->json([
                'status' => 'success',
                'message' => 'Consignment successfully booked with courier',
                'consignment_id' => $result->consignmentId,
                'tracking_code' => $result->trackingCode,
                'delivery_fee_bdt' => $result->deliveryFeeBdt,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result->errorMessage ?? 'Failed to book courier consignment',
        ], 422);
    }

    /**
     * Bulk consignment booking endpoint.
     */
    public function bulkBook(
        Request $request,
        BulkBookCourierConsignmentsAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'courier_code' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
        ]);

        $courierCode = ! empty($validated['courier_code']) ? (string) $validated['courier_code'] : null;
        $options = (array) ($validated['options'] ?? []);

        $summary = $action($validated['order_ids'], $courierCode, $options);

        return response()->json([
            'status' => 'success',
            'summary' => $summary,
        ]);
    }
}
