<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers for every web response:
 * HSTS (production + HTTPS only), MIME sniffing, clickjacking, referrer and permissions policy,
 * plus a nonce-based Content Security Policy (Report-Only unless `security.csp_enforce`).
 *
 * The nonce is shared with Vite (`Vite::useCspNonce`) so every @vite script/style tag is allowed.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self), usb=()');

        if (app()->environment('production') && $request->isSecure()) {
            $hsts = 'max-age='.config('security.hsts_max_age');
            if (config('security.hsts_include_subdomains')) {
                $hsts .= '; includeSubDomains';
            }
            $headers->set('Strict-Transport-Security', $hsts);
        }

        // Local dev serves scripts from the Vite dev server (HMR); no CSP there.
        if (! app()->environment('local')) {
            $headerName = config('security.csp_enforce')
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';

            $headers->set($headerName, $this->contentSecurityPolicy($nonce));
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $captchaScripts = 'https://www.google.com https://www.gstatic.com https://challenges.cloudflare.com';

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' {$captchaScripts}",
            // Vue :style bindings render inline style attributes
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            // Product media may live on any configured cloud disk / CDN (S3, R2, GCS, Spaces)
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https://www.google.com https://challenges.cloudflare.com",
            'frame-src https://www.google.com https://challenges.cloudflare.com',
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
        ];

        if ($reportUri = config('security.csp_report_uri')) {
            $directives[] = "report-uri {$reportUri}";
        }

        return implode('; ', $directives);
    }
}
