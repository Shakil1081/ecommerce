<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Actions\TrackOrderAction;
use App\Modules\Customer\Http\Requests\TrackOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderTrackingController extends Controller
{
    /**
     * Display order tracking portal.
     */
    public function index(Request $request, TrackOrderAction $trackOrderAction): Response|JsonResponse
    {
        $orderNumber = (string) $request->input('order_number', '');
        $phone = (string) ($request->input('phone') ?? $request->input('customer_phone') ?? '');

        $tracking = null;
        $errorMessage = null;

        if ($orderNumber !== '' && $phone !== '') {
            $result = $trackOrderAction($orderNumber, $phone);

            if ($result) {
                $tracking = $result->toArray();
            } else {
                $errorMessage = 'We could not find an order matching that Order Number and Phone Number. Please verify and try again.';
            }

            if ($request->wantsJson()) {
                if ($result) {
                    return response()->json([
                        'success' => true,
                        'data' => $tracking,
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 404);
            }
        }

        return Inertia::render('Storefront/OrderTracking', [
            'tracking' => $tracking,
            'query' => [
                'order_number' => $orderNumber,
                'phone' => $phone,
            ],
            'errorMessage' => $errorMessage,
        ]);
    }

    /**
     * Submit tracking search from form.
     */
    public function search(TrackOrderRequest $request, TrackOrderAction $trackOrderAction): Response|JsonResponse
    {
        $orderNumber = $request->validated('order_number');
        $phone = $request->validated('phone');

        $result = $trackOrderAction($orderNumber, $phone);

        if ($request->wantsJson()) {
            if ($result) {
                return response()->json([
                    'success' => true,
                    'data' => $result->toArray(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'We could not find an order matching that Order Number and Phone Number. Please verify and try again.',
            ], 404);
        }

        return Inertia::render('Storefront/OrderTracking', [
            'tracking' => $result?->toArray(),
            'query' => [
                'order_number' => $orderNumber,
                'phone' => $phone,
            ],
            'errorMessage' => $result ? null : 'We could not find an order matching that Order Number and Phone Number. Please verify and try again.',
        ]);
    }
}
