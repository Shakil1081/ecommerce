<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\ProcessAdvanceDeliveryPaymentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcessAdvanceDeliveryPaymentController extends Controller
{
    public function __invoke(
        Request $request,
        Order $order,
        ProcessAdvanceDeliveryPaymentAction $action
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'gateway_code' => ['required', 'string', 'in:bkash,nagad,sslcommerz'],
            'amount' => ['nullable', 'numeric', 'min:50'],
        ]);

        $gatewayCode = (string) $validated['gateway_code'];
        $amount = isset($validated['amount']) ? (float) $validated['amount'] : null;

        $result = $action($order, $gatewayCode, $amount);

        if (! $result->success || empty($result->redirectUrl)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $result->errorMessage ?? 'Failed to initiate advance payment.',
                ], 422);
            }

            return redirect()->back()->with('error', $result->errorMessage ?? 'Failed to initiate advance payment.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect_url' => $result->redirectUrl,
                'transaction_id' => $result->transactionId,
            ]);
        }

        return redirect()->away($result->redirectUrl);
    }
}
