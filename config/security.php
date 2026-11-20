<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    | The CSP (nonce-based script-src) ships in Report-Only mode so it cannot break checkout,
    | CAPTCHA, or printable shipping labels on first deploy. Watch the browser console / report
    | endpoint, then set CSP_ENFORCE=true to enforce it.
    */
    'csp_enforce' => (bool) env('CSP_ENFORCE', false),

    // Optional CSP violation report endpoint (e.g. a report-uri.com URL)
    'csp_report_uri' => env('CSP_REPORT_URI'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    | Sent only in production over HTTPS. Browsers then refuse plain HTTP for this domain for
    | `max_age` seconds, so make sure HTTPS works on every subdomain before enabling `include_subdomains`.
    */
    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),
    'hsts_include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    | Behind Cloudflare / nginx / a load balancer, set this so request()->ip() returns the real
    | customer IP (used by fraud velocity limits, rate limiting and audit logs). Comma-separated
    | IPs/CIDRs, or "*" to trust the immediate upstream proxy. Leave empty when not behind a proxy.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

];
