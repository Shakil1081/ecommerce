<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Logistics\Models\CourierGatewaySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Authenticates inbound courier status webhooks (Steadfast, Pathao, ...).
 *
 * Courier webhooks move orders to delivered / returned and drive COD reconciliation and restocking,
 * so they fail CLOSED: outside local/testing, a courier without a configured `webhook_secret`
 * rejects every webhook. Secrets are compared in constant time.
 */
final class CourierWebhookAuthenticator
{
    /**
     * @param  array<int, string>  $secretHeaders  Courier-specific header names carrying the shared secret
     */
    public function isAuthentic(Request $request, ?CourierGatewaySetting $setting, array $secretHeaders = ['X-Webhook-Secret']): bool
    {
        $expected = (string) ($setting?->webhook_secret ?? '');
        $courier = $setting?->code ?? 'unknown';

        if ($expected === '') {
            if (app()->environment('local', 'testing')) {
                return true;
            }

            Log::critical('Courier webhook rejected: no webhook_secret configured for courier', ['courier' => $courier]);

            return false;
        }

        $candidates = array_map(static fn (string $header): ?string => $request->header($header), $secretHeaders);
        $candidates[] = $request->bearerToken();
        // Some courier panels can only append a query string to the callback URL
        $candidates[] = is_string($request->query('secret')) ? $request->query('secret') : null;

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && hash_equals($expected, $candidate)) {
                return true;
            }
        }

        Log::warning('Courier webhook rejected: invalid secret', ['courier' => $courier, 'ip' => $request->ip()]);

        return false;
    }
}
