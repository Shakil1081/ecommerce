<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
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
    public function success(Request $request, SslcommerzPaymentAdapter $adapter): RedirectResponse
    {
        $tranId = (string) $request->input('tran_id', '');

        $transaction = PaymentTransaction::with('order')
            ->where('gateway_code', $adapter->getGatewayCode())
            ->where('transaction_id', $tranId)
            ->first();

        if (! $transaction || ! $transaction->order) {
            return redirect()->route('storefront.checkout')->with('error', 'SSLCommerz payment transaction not found.');
        }

        $order = $transaction->order;
        $result = $adapter->verifyPayment($request, $order);

        if ($result->isSuccessful) {
            $this->applySuccessfulPayment($order, $transaction, (string) $result->gatewayTrxId);

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
        $adapter->verifyPayment($request);

        return redirect()->route('storefront.checkout')
            ->with('error', 'Payment failed on SSLCommerz gateway. Please try again.');
    }

    /**
     * Process cancelled payment redirect.
     */
    public function cancel(Request $request, SslcommerzPaymentAdapter $adapter): RedirectResponse
    {
        $adapter->verifyPayment($request);

        return redirect()->route('storefront.checkout')
            ->with('error', 'Payment was cancelled by user.');
    }

    /**
     * Process asynchronous Instant Payment Notification (IPN).
     */
    public function ipn(Request $request, SslcommerzPaymentAdapter $adapter): JsonResponse
    {
        $tranId = (string) $request->input('tran_id', '');

        $transaction = PaymentTransaction::with('order')
            ->where('gateway_code', $adapter->getGatewayCode())
            ->where('transaction_id', $tranId)
            ->first();

        if (! $transaction || ! $transaction->order) {
            return response()->json(['status' => 'ERROR', 'message' => 'Transaction not found'], 404);
        }

        $order = $transaction->order;
        $result = $adapter->verifyPayment($request, $order);

        if ($result->isSuccessful) {
            $this->applySuccessfulPayment($order, $transaction, (string) $result->gatewayTrxId);

            return response()->json(['status' => 'SUCCESS', 'message' => 'Payment reconciled via IPN']);
        }

        return response()->json(['status' => 'FAILED', 'message' => $result->errorMessage], 400);
    }

    /**
     * Update order status and advance/COD balances upon verified payment.
     */
    protected function applySuccessfulPayment(Order $order, PaymentTransaction $transaction, string $gatewayTrxId): void
    {
        if ($order->payment_status === Order::PAYMENT_PAID) {
            return;
        }

        if ($transaction->payment_purpose === PaymentTransaction::PURPOSE_ORDER_FULL) {
            $order->update([
                'payment_status' => Order::PAYMENT_PAID,
                'advance_paid_bdt' => $order->total_bdt,
                'payable_cod_amount_bdt' => 0.0000,
                'status' => Order::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);
        } elseif ($transaction->payment_purpose === PaymentTransaction::PURPOSE_ADVANCE_DELIVERY_CHARGE) {
            $newAdvance = bcadd((string) $order->advance_paid_bdt, (string) $transaction->amount_bdt, 4);
            $remainingCod = bcsub((string) $order->total_bdt, $newAdvance, 4);

            $order->update([
                'payment_status' => Order::PAYMENT_PARTIAL_ADVANCE,
                'advance_paid_bdt' => $newAdvance,
                'payable_cod_amount_bdt' => max('0.0000', $remainingCod),
                'status' => Order::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => Order::STATUS_PENDING,
            'to_status' => Order::STATUS_CONFIRMED,
            'reason' => "SSLCommerz payment confirmed. Bank TrxID: [{$gatewayTrxId}]",
            'created_at' => now(),
        ]);
    }
}
