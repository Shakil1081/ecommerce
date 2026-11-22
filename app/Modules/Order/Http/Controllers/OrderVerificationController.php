<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Actions\VerifyOtpAction;
use App\Modules\Order\Actions\SendOrderVerificationOtpAction;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer-facing SMS OTP confirmation for medium/high fraud-risk orders.
 *
 * Knowing the order number is not enough: the code only goes to the phone on the order,
 * each code allows 3 attempts, and both endpoints are rate limited.
 */
final class OrderVerificationController extends Controller
{
    public function show(string $order, SendOrderVerificationOtpAction $sendOtp): Response|RedirectResponse
    {
        $orderModel = $this->findOrder($order);

        if (! $orderModel->requiresOtpVerification()) {
            return redirect()->route('storefront.order-confirmed', ['order' => $orderModel->order_number]);
        }

        return Inertia::render('Storefront/OrderVerify', [
            'orderNumber' => $orderModel->order_number,
            'maskedPhone' => $this->maskPhone($orderModel->customer_phone),
            'totalBdt' => (string) $orderModel->total_bdt,
            'resendAvailableIn' => $sendOtp->secondsUntilResendAllowed($orderModel),
        ]);
    }

    public function verify(Request $request, string $order, VerifyOtpAction $verifyOtp): RedirectResponse
    {
        $orderModel = $this->findOrder($order);

        if (! $orderModel->requiresOtpVerification()) {
            return redirect()->route('storefront.order-confirmed', ['order' => $orderModel->order_number]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ], [
            'code.regex' => 'Please enter the code from the SMS (numbers only).',
        ]);

        $result = $verifyOtp(
            phoneNumber: $orderModel->customer_phone,
            code: $validated['code'],
            purpose: SendOrderVerificationOtpAction::PURPOSE,
            orderId: $orderModel->id,
        );

        if (! $result->success) {
            return back()->withErrors(['code' => $result->message]);
        }

        DB::transaction(function () use ($orderModel, $request): void {
            $orderModel->update(['otp_verified_at' => now()]);

            OrderStatusHistory::create([
                'order_id' => $orderModel->id,
                'from_status' => $orderModel->status,
                'to_status' => $orderModel->status,
                'changed_by_user_id' => $request->user()?->id,
                'reason' => 'Customer confirmed the order with SMS OTP',
                'metadata' => ['ip' => $request->ip()],
                'created_at' => now(),
            ]);
        });

        return redirect()->route('storefront.order-confirmed', ['order' => $orderModel->order_number]);
    }

    public function resend(Request $request, string $order, SendOrderVerificationOtpAction $sendOtp): RedirectResponse
    {
        $orderModel = $this->findOrder($order);

        if (! $orderModel->requiresOtpVerification()) {
            return redirect()->route('storefront.order-confirmed', ['order' => $orderModel->order_number]);
        }

        $wait = $sendOtp->secondsUntilResendAllowed($orderModel);
        if ($wait > 0) {
            return back()->withErrors(['code' => "Please wait {$wait} seconds before requesting a new code."]);
        }

        if (! $sendOtp($orderModel, $request->ip())) {
            return back()->withErrors(['code' => 'We could not send the SMS right now. Please try again, or our team will call you to confirm.']);
        }

        return back()->with('success', 'A new verification code has been sent.');
    }

    private function findOrder(string $orderNumber): Order
    {
        return Order::query()->where('order_number', $orderNumber)->firstOrFail();
    }

    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 5) {
            return $phone;
        }

        return substr($phone, 0, 3).str_repeat('*', $length - 6).substr($phone, -3);
    }
}
