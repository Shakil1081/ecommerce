<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Actions\SettleGatewayPaymentAction;
use App\Modules\Payment\Adapters\NagadPaymentAdapter;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NagadCallbackController extends Controller
{
    public function __invoke(Request $request, NagadPaymentAdapter $adapter, SettleGatewayPaymentAction $settle): RedirectResponse
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
        $result = $settle($transaction, fn () => $adapter->verifyPayment($request, $order), 'Nagad');

        if ($result->isSuccessful) {
            return redirect()->route('storefront.order-confirmed', ['order' => $order->order_number])
                ->with('success', 'Nagad payment confirmed successfully!');
        }

        return redirect()->route('storefront.checkout')
            ->with('error', $result->errorMessage ?? 'Nagad payment was not completed.');
    }
}
