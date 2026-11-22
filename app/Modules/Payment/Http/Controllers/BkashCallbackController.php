<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Actions\SettleGatewayPaymentAction;
use App\Modules\Payment\Adapters\BkashPaymentAdapter;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BkashCallbackController extends Controller
{
    public function __invoke(Request $request, BkashPaymentAdapter $adapter, SettleGatewayPaymentAction $settle): RedirectResponse
    {
        $paymentId = (string) $request->input('paymentID', '');

        $transaction = PaymentTransaction::with('order')
            ->where('gateway_code', $adapter->getGatewayCode())
            ->where('transaction_id', $paymentId)
            ->first();

        if (! $transaction || ! $transaction->order) {
            return redirect()->route('storefront.checkout')->with('error', 'bKash payment reference not found.');
        }

        $order = $transaction->order;
        $result = $settle($transaction, fn () => $adapter->verifyPayment($request, $order), 'bKash');

        if ($result->isSuccessful) {
            return redirect()->route('storefront.order-confirmed', ['order' => $order->order_number])
                ->with('success', 'bKash payment confirmed successfully!');
        }

        return redirect()->route('storefront.checkout')
            ->with('error', $result->errorMessage ?? 'bKash payment was not completed.');
    }
}
