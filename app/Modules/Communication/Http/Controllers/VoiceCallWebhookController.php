<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\VoiceCallingSetting;
use App\Modules\Communication\Models\VoiceCallLog;
use App\Modules\Order\Actions\TransitionOrderStatusAction;
use App\Modules\Order\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VoiceCallWebhookController extends Controller
{
    /**
     * Generate dynamic TwiML XML instructions for outbound voice call.
     */
    public function twiml(Request $request): Response
    {
        $orderId = $request->query('order_id') ?: $request->input('order_id');
        $order = $orderId ? Order::find($orderId) : null;
        $setting = VoiceCallingSetting::active()->first();

        $customerName = $order ? $order->customer_name : 'গ্রাহক';
        $orderNumber = $order ? $order->order_number : 'অর্ডার';
        $totalAmount = $order ? number_format((float) $order->total_bdt, 0) : '0';
        $storeName = config('app.name', 'অনলাইন শপ');

        $actionUrl = url('/webhooks/voice/gather'.($order ? "?order_id={$order->id}" : ''));

        $promptBn = "হ্যালো {$customerName}, {$storeName} থেকে আপনার {$orderNumber} নম্বর অর্ডারের মোট মূল্য {$totalAmount} টাকা। অর্ডারটি কনফার্ম করতে ১ চাপুন, বাতিল করতে ২ চাপুন।";
        $fallbackBn = 'কোনো ইনপুট পাওয়া যায়নি। আমাদের প্রতিনিধি আপনাকে শীঘ্রই কল করবেন। ধন্যবাদ।';

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<Response>\n";
        $xml .= "    <Gather numDigits=\"1\" action=\"{$actionUrl}\" method=\"POST\" timeout=\"10\">\n";
        $xml .= "        <Say language=\"bn-IN\" voice=\"Polly.Aditi\">{$promptBn}</Say>\n";
        $xml .= "    </Gather>\n";
        $xml .= "    <Say language=\"bn-IN\" voice=\"Polly.Aditi\">{$fallbackBn}</Say>\n";
        $xml .= '</Response>';

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Process DTMF touch-tone response (1 = Confirm, 2 = Cancel) and transition order.
     */
    public function gather(Request $request, TransitionOrderStatusAction $transitionAction): Response
    {
        $orderId = $request->query('order_id') ?: $request->input('order_id');
        $digits = (string) $request->input('Digits', '');
        $callSid = $request->input('CallSid');

        $order = $orderId ? Order::find($orderId) : null;
        $callLog = null;

        if ($callSid) {
            $callLog = VoiceCallLog::where('call_sid', $callSid)->first();
        }
        if (! $callLog && $order) {
            $callLog = VoiceCallLog::where('order_id', $order->id)->latest('id')->first();
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>\n";

        if ($digits === '1') {
            if ($order && $order->status === Order::STATUS_PENDING) {
                $transitionAction(
                    order: $order,
                    targetStatus: Order::STATUS_CONFIRMED,
                    changedBy: null,
                    reason: 'Confirmed via automated AI voice phone call (Keypress 1).'
                );
            }

            if ($callLog) {
                $callLog->update([
                    'verification_result' => VoiceCallLog::RESULT_CONFIRMED,
                    'dtmf_digit' => '1',
                    'response_mode' => 'dtmf',
                    'notes' => 'Customer confirmed order by pressing 1.',
                ]);
            }

            $xml .= "    <Say language=\"bn-IN\" voice=\"Polly.Aditi\">আপনার অর্ডারটি সফলভাবে কনফার্ম করা হয়েছে। দ্রুত ডেলিভারির ব্যবস্থা করা হচ্ছে। ধন্যবাদ।</Say>\n";
        } elseif ($digits === '2') {
            if ($order && in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED], true)) {
                $transitionAction(
                    order: $order,
                    targetStatus: Order::STATUS_CANCELLED,
                    changedBy: null,
                    reason: 'Cancelled by customer via automated AI voice phone call (Keypress 2).'
                );
            }

            if ($callLog) {
                $callLog->update([
                    'verification_result' => VoiceCallLog::RESULT_CANCELLED,
                    'dtmf_digit' => '2',
                    'response_mode' => 'dtmf',
                    'notes' => 'Customer cancelled order by pressing 2.',
                ]);
            }

            $xml .= "    <Say language=\"bn-IN\" voice=\"Polly.Aditi\">আপনার অনুরোধ অনুযায়ী অর্ডারটি বাতিল করা হয়েছে। ধন্যবাদ।</Say>\n";
        } else {
            $xml .= "    <Say language=\"bn-IN\" voice=\"Polly.Aditi\">অকার্যকর বোতাম প্রেস করা হয়েছে। আমাদের কাস্টমার কেয়ার প্রতিনিধি আপনার সাথে যোগাযোগ করবেন। ধন্যবাদ।</Say>\n";
        }

        $xml .= '</Response>';

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Handle Twilio call lifecycle status callbacks (ringing, answered, completed).
     */
    public function status(Request $request): Response
    {
        $callSid = $request->input('CallSid');
        $callStatus = $request->input('CallStatus');
        $duration = (int) $request->input('CallDuration', 0);
        $recordingUrl = $request->input('RecordingUrl');

        if ($callSid) {
            $callLog = VoiceCallLog::where('call_sid', $callSid)->first();
            if ($callLog) {
                $updateData = [
                    'status' => (string) $callStatus,
                ];

                if ($duration > 0) {
                    $updateData['duration_seconds'] = $duration;
                }

                if (! empty($recordingUrl)) {
                    $updateData['recording_url'] = $recordingUrl;
                }

                if (in_array($callStatus, ['busy', 'no-answer', 'canceled', 'failed'], true)) {
                    $updateData['verification_result'] = VoiceCallLog::RESULT_UNATTENDED;
                }

                $callLog->update($updateData);
            }
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }
}
