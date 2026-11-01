<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Actions\LogAuditTrailAction;
use App\Modules\Common\Models\BotSecuritySetting;
use App\Modules\Common\Security\HoneypotTimingGuardService;
use App\Modules\Common\Services\BotCaptchaVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController extends Controller
{
    /**
     * Show the secure admin login form with honeypot token and reCAPTCHA settings.
     */
    public function create(HoneypotTimingGuardService $timingService): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        $botSetting = BotSecuritySetting::getActive();
        $isBotProtected = $botSetting !== null && $botSetting->isPageProtected(BotSecuritySetting::PAGE_LOGIN);

        return Inertia::render('Auth/Login', [
            'botSecurity' => [
                'enabled' => $isBotProtected,
                'provider' => $botSetting?->provider ?? 'recaptcha_v3',
                'site_key' => $botSetting?->site_key ?? '',
            ],
            'timingToken' => $timingService->generateTimingToken(),
            'honeypotField' => HoneypotTimingGuardService::DEFAULT_HONEYPOT_FIELD,
        ]);
    }

    /**
     * Handle incoming authentication request with multi-layer enterprise security defenses:
     * 1. Rate limiting & Brute Force Throttling
     * 2. Invisible Honeypot Trap
     * 3. Form Submission Timing Guard
     * 4. Google reCAPTCHA / Cloudflare Turnstile Verification
     * 5. Constant-time Authentication & Session Fixation Protection
     * 6. Tamper-proof Audit Trail Logging
     */
    public function store(
        Request $request,
        BotCaptchaVerificationService $captchaService,
        HoneypotTimingGuardService $timingService,
        LogAuditTrailAction $auditAction
    ): RedirectResponse {
        $email = Str::lower(trim((string) $request->input('email', '')));
        $clientIp = (string) $request->ip();
        $throttleKey = Str::transliterate("{$email}|{$clientIp}");

        // --- Defense 1: Brute Force & Credential Stuffing Throttling (Max 5 attempts / 60s)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $auditAction(
                event: 'auth.login.throttled',
                description: "Login rate limit exceeded for [{$email}]. Locked for {$seconds}s from IP {$clientIp}",
                options: ['ip_address' => $clientIp, 'user_agent' => $request->userAgent()]
            );

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ])->status(429);
        }

        // --- Defense 2: Invisible Honeypot Trap (Automated Spambot Detection)
        $honeypotValue = $request->input(HoneypotTimingGuardService::DEFAULT_HONEYPOT_FIELD);
        if (! empty($honeypotValue)) {
            RateLimiter::hit($throttleKey, 300); // Penalty: 5-minute lockout for honeypot violation

            $auditAction(
                event: 'auth.login.bot_detected',
                description: "Invisible honeypot trap triggered from IP {$clientIp}. Spambot rejected.",
                options: ['ip_address' => $clientIp, 'user_agent' => $request->userAgent()]
            );

            throw ValidationException::withMessages([
                'email' => 'Automated bot submission detected. Request rejected.',
            ]);
        }

        // --- Defense 3: Form Submission Timing Guard (Sub-human speed script detection)
        $timingToken = $request->input(HoneypotTimingGuardService::DEFAULT_TIMING_FIELD);
        if (! empty($timingToken) && ! app()->environment('testing')) {
            $timingResult = $timingService->validateTiming($request, 1.2);
            if (! $timingResult['valid']) {
                $auditAction(
                    event: 'auth.login.timing_failed',
                    description: "Timing guard failed for [{$email}]: {$timingResult['error']}",
                    options: ['ip_address' => $clientIp, 'user_agent' => $request->userAgent()]
                );

                throw ValidationException::withMessages([
                    'email' => 'Submission completed too quickly. Please submit again.',
                ]);
            }
        }

        // --- Defense 4: Google reCAPTCHA / Turnstile Verification
        $botSetting = BotSecuritySetting::getActive();
        if ($botSetting !== null && $botSetting->isPageProtected(BotSecuritySetting::PAGE_LOGIN)) {
            $token = (string) ($request->input('g-recaptcha-response') ?: $request->input('captcha_token', ''));

            if (! app()->environment('testing')) {
                $captchaResult = $captchaService->verifyToken($token, $clientIp, $botSetting);

                if (! $captchaResult->success) {
                    $auditAction(
                        event: 'auth.login.captcha_failed',
                        description: "CAPTCHA challenge failed for [{$email}] from IP {$clientIp}: {$captchaResult->errorMessage}",
                        options: ['ip_address' => $clientIp, 'user_agent' => $request->userAgent()]
                    );

                    throw ValidationException::withMessages([
                        'captcha' => $captchaResult->errorMessage ?: 'Google reCAPTCHA verification failed. Please try again.',
                    ]);
                }
            }
        }

        // --- Defense 5: Strict Input Sanitization & Validation
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $remember = $request->boolean('remember');

        // Record attempt against rate limiter
        RateLimiter::hit($throttleKey, 60);

        // Constant-time password check & authentication
        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password']], $remember)) {
            $auditAction(
                event: 'auth.login.failed',
                description: "Failed login attempt for [{$email}] from IP {$clientIp}",
                options: ['ip_address' => $clientIp, 'user_agent' => $request->userAgent()]
            );

            throw ValidationException::withMessages([
                'email' => __('auth.failed', [], 'Invalid email address or password provided.'),
            ]);
        }

        // --- Defense 6: Session Fixation Defense & State Reset
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        // --- Defense 7: Immutable Audit Trail Logging
        $auditAction(
            event: 'auth.login.success',
            description: "Super Admin [{$user->name}] authenticated successfully from IP {$clientIp}",
            auditable: $user,
            options: ['user' => $user, 'ip_address' => $clientIp, 'user_agent' => $request->userAgent()]
        );

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', "Welcome back, {$user->name}! Secure session established.");
    }

    /**
     * Destroy authenticated session (Logout) with session invalidation & audit logging.
     */
    public function destroy(Request $request, LogAuditTrailAction $auditAction): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $clientIp = (string) $request->ip();

        if ($user !== null) {
            $auditAction(
                event: 'auth.logout',
                description: "Admin [{$user->name}] signed out safely from IP {$clientIp}",
                auditable: $user,
                options: ['user' => $user, 'ip_address' => $clientIp, 'user_agent' => $request->userAgent()]
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('info', 'You have been safely signed out. Secure session terminated.');
    }
}
