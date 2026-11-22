<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Actions\SettleGatewayPaymentAction;
use App\Modules\Payment\Adapters\SslcommerzPaymentAdapter;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SslcommerzCallbackController extends Controller
{
    /**
     * Process successful payment redirect.
     */
    public function success(Request $request, SslcommerzPaymentAdapter $adapter, SettleGatewayPaymentAction $settle): RedirectResponse
    {
        $transaction = $this->findTransaction($request, $adapter);

        if (! $transaction || ! $transaction->order) {
            return redirect()->route('storefront.checkout')->with('error', 'SSLCommerz payment transaction not found.');
        }

        $order = $transaction->order;
        $result = $settle($transaction, fn () => $adapter->verifyPayment($request, $order), 'SSLCommerz');

        if ($result->isSuccessful) {
            return redirect()->route('storefront.order-confirmed', ['order' => $order->order_number])
                ->with('success', 'SSLCommerz payment confirmed successfully!');
        }

        return redirect()->route('storefront.checkout')
            ->with('error', $result->errorMessage ?? 'SSLCommerz validation failed.');
    }

    /**
     * Process failed payment redirect.
     */
    public function fail(Request $request, SslcommerzPaymentAdapter $adapter): RedirectResponse
    {
        $this->markUnsettledTransaction($request, $adapter);

        return redirect()->route('storefront.checkout')
            ->with('error', 'Payment failed on SSLCommerz gateway. Please try again.');
    }

    /**
     * Process cancelled payment redirect.
     */
    public function cancel(Request $request, SslcommerzPaymentAdapter $adapter): RedirectResponse
    {
        $this->markUnsettledTransaction($request, $adapter);

        return redirect()->route('storefront.checkout')
            ->with('error', 'Payment was cancelled by user.');
    }

    /**
     * Process asynchronous Instant Payment Notification (IPN).
     */
    public function ipn(Request $request, SslcommerzPaymentAdapter $adapter, SettleGatewayPaymentAction $settle): JsonResponse
    {
        $transaction = $this->findTransaction($request, $adapter);

        if (! $transaction || ! $transaction->order) {
            return response()->json(['status' => 'ERROR', 'message' => 'Transaction not found'], 404);
        }

        $order = $transaction->order;
        $result = $settle($transaction, fn () => $adapter->verifyPayment($request, $order), 'SSLCommerz');

        if ($result->isSuccessful) {
            return response()->json(['status' => 'SUCCESS', 'message' => 'Payment reconciled via IPN']);
        }

        return response()->json(['status' => 'FAILED', 'message' => $result->errorMessage], 400);
    }

    private function findTransaction(Request $request, SslcommerzPaymentAdapter $adapter): ?PaymentTransaction
    {
        return PaymentTransaction::with('order')
            ->where('gateway_code', $adapter->getGatewayCode())
            ->where('transaction_id', (string) $request->input('tran_id', ''))
            ->first();
    }

    /**
     * Record a fail/cancel redirect, but never let an unauthenticated browser redirect
     * downgrade a transaction that was already settled (e.g. via IPN).
     */
    private function markUnsettledTransaction(Request $request, SslcommerzPaymentAdapter $adapter): void
    {
        $transaction = $this->findTransaction($request, $adapter);

        if ($transaction !== null && $transaction->status === PaymentTransaction::STATUS_SUCCESSFUL) {
            return;
        }

        $adapter->verifyPayment($request);
    }
}
