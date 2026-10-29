<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\WhatsAppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle Meta WhatsApp webhook verification challenge (GET).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $setting = WhatsAppSetting::active()->first();
        $expectedToken = $setting?->webhook_verify_token;

        if ($mode === 'subscribe' && ! empty($expectedToken) && hash_equals($expectedToken, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming WhatsApp webhook status notifications (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Meta wraps updates in entry.*.changes.*.value
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $statuses = $value['statuses'] ?? [];

                foreach ($statuses as $status) {
                    $messageId = $status['id'] ?? null;
                    $recipientId = $status['recipient_id'] ?? null;
                    $deliveryStatus = $status['status'] ?? null; // sent, delivered, read, failed

                    // We can log or trigger events if needed
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
