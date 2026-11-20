<?php

declare(strict_types=1);

namespace App\Modules\Payment\Actions;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Payment\DTOs\PaymentVerificationResultDto;
use App\Modules\Payment\Models\PaymentTransaction;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single settlement path for every gateway callback / IPN (bKash, Nagad, SSLCommerz).
 *
 * Guarantees:
 *  1. Idempotency: the payment transaction row is locked (`lockForUpdate`) for the whole verify + apply
 *     cycle, and an already-settled transaction is never applied twice. Protects against double-counting
 *     advance delivery charges when a callback is replayed or the redirect and IPN race each other.
 *  2. Amount integrity: the amount confirmed server-to-server by the gateway must cover the expected
 *     `amount_bdt`; a valid-but-smaller payment (e.g. a ৳10 payment replayed against a ৳50,000 order)
 *     is rejected and logged.
 *  3. Atomicity: the order balance update and status history are written in the same DB transaction.
 */
final class SettleGatewayPaymentAction
{
    /**
     * @param  Closure(): PaymentVerificationResultDto  $verify  Server-to-server gateway verification
     */
    public function __invoke(
        PaymentTransaction $transaction,
        Closure $verify,
        string $gatewayLabel,
    ): PaymentVerificationResultDto {
        return DB::transaction(function () use ($transaction, $verify, $gatewayLabel): PaymentVerificationResultDto {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === PaymentTransaction::STATUS_SUCCESSFUL) {
                // Already settled by an earlier callback / IPN: acknowledge without re-applying
                return PaymentVerificationResultDto::successful(
                    transactionId: (string) $locked->ulid,
                    gatewayTrxId: (string) $locked->transaction_id,
                    amountPaid: (float) $locked->amount_bdt,
                    customerAccount: $locked->customer_account,
                );
            }

            $result = $verify();

            if (! $result->isSuccessful) {
                return $result;
            }

            $expected = number_format((float) $locked->amount_bdt, 4, '.', '');
            $paid = number_format((float) $result->amountPaid, 4, '.', '');

            if (bccomp($paid, $expected, 2) < 0) {
                $message = "{$gatewayLabel} amount mismatch: gateway confirmed ৳{$paid}, expected ৳{$expected}.";

                PaymentTransaction::query()->whereKey($locked->getKey())->update([
                    'status' => PaymentTransaction::STATUS_FAILED,
                    'error_message' => $message,
                ]);

                Log::critical('Payment amount mismatch rejected', [
                    'gateway' => $gatewayLabel,
                    'transaction_ulid' => $locked->ulid,
                    'order_id' => $locked->order_id,
                    'expected_bdt' => $expected,
                    'paid_bdt' => $paid,
                    'gateway_trx_id' => $result->gatewayTrxId,
                ]);

                return PaymentVerificationResultDto::failed(
                    errorMessage: 'Payment amount could not be verified. Please contact customer support.',
                    transactionId: (string) $locked->ulid,
                    gatewayTrxId: $result->gatewayTrxId,
                    rawPayload: $result->rawPayload,
                );
            }

            /** @var Order $order */
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $this->applyToOrder($order, $locked, $gatewayLabel, (string) $result->gatewayTrxId);

            return $result;
        });
    }

    private function applyToOrder(Order $order, PaymentTransaction $transaction, string $gatewayLabel, string $gatewayTrxId): void
    {
        $fromStatus = $order->status;

        if ($transaction->payment_purpose === PaymentTransaction::PURPOSE_ORDER_FULL) {
            $order->update([
                'payment_status' => Order::PAYMENT_PAID,
                'advance_paid_bdt' => $order->total_bdt,
                'payable_cod_amount_bdt' => '0.0000',
                'status' => Order::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);
        } elseif ($transaction->payment_purpose === PaymentTransaction::PURPOSE_ADVANCE_DELIVERY_CHARGE) {
            $newAdvance = bcadd((string) $order->advance_paid_bdt, (string) $transaction->amount_bdt, 4);
            $remainingCod = bcsub((string) $order->total_bdt, $newAdvance, 4);

            $order->update([
                'payment_status' => Order::PAYMENT_PARTIAL_ADVANCE,
                'advance_paid_bdt' => $newAdvance,
                'payable_cod_amount_bdt' => bccomp($remainingCod, '0', 4) > 0 ? $remainingCod : '0.0000',
                'status' => Order::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);
        } else {
            return;
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => Order::STATUS_CONFIRMED,
            'reason' => "{$gatewayLabel} online payment confirmed. TrxID: [{$gatewayTrxId}]",
            'created_at' => now(),
        ]);
    }
}
