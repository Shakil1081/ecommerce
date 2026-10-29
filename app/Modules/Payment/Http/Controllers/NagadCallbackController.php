<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Payment\Adapters\NagadPaymentAdapter;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NagadCallbackController extends Controller
{
    public function __invoke(Request $request, NagadPaymentAdapter $adapter): RedirectResponse
    {
        $paymentRefId = (string) ($request->input('payment_ref_id') ?? $request->input('paymentReferenceId', ''));

        $transaction = PaymentTransaction::with('order')
            ->where('gateway_code', $adapter->getGatewayCode())
            ->where('transaction_id', $paymentRefId)
            ->first();

        if (! $transaction || ! $transaction->order) {
            return redirect()->route('storefront.checkout')->with('error', 'Nagad payment reference not found.');
        }

        $order = $transaction->order;
        $result = $adapter->verifyPayment($request, $order);

        if ($result->isSuccessful) {
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
                'reason' => "Nagad digital payment confirmed. TrxID: [{$result->gatewayTrxId}]",
                'created_at' => now(),
            ]);

            return redirect()->route('storefront.order-confirmed', ['order' => $order->order_number])
                ->with('success', 'Nagad payment confirmed successfully!');
        }

        return redirect()->route('storefront.checkout')
            ->with('error', $result->errorMessage ?? 'Nagad payment was not completed.');
    }
}
