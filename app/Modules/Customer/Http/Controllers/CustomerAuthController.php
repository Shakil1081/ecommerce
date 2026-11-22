<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Common\Security\HoneypotTimingGuardService;
use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Storefront customer accounts: sign up, log in (email or mobile number), log out, password reset.
 * Staff keep using the separate /login screen; customers never receive a role.
 */
final class CustomerAuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function showLogin(HoneypotTimingGuardService $timingGuard): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account.dashboard');
        }

        return Inertia::render('Storefront/Account/Auth/Login', [
            'timingToken' => $timingGuard->generateTimingToken(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'login.required' => 'Please enter your email or mobile number.',
        ]);

        $identifier = Str::lower(trim($validated['login']));
        $throttleKey = 'customer-login|'.Str::transliterate($identifier).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'login' => 'Too many login attempts. Please try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ])->status(429);
        }

        $credentials = str_contains($identifier, '@')
            ? ['email' => $identifier]
            : ['phone' => BangladeshPhoneNumberRule::toLocalFormat(BangladeshPhoneNumberRule::cleanNumber($identifier))];

        if (! Auth::attempt($credentials + ['password' => $validated['password']], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'login' => 'The email/mobile number or password is incorrect.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        if ($user->isStaff()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('account.dashboard'))
            ->with('success', "Welcome back, {$user->name}!");
    }

    public function showRegister(HoneypotTimingGuardService $timingGuard): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account.dashboard');
        }

        return Inertia::render('Storefront/Account/Auth/Register', [
            'timingToken' => $timingGuard->generateTimingToken(),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'phone' => BangladeshPhoneNumberRule::toLocalFormat(
                BangladeshPhoneNumberRule::cleanNumber((string) $request->input('phone'))
            ),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'email.unique' => 'An account with this email already exists. Please log in instead.',
            'phone.unique' => 'An account with this mobile number already exists. Please log in instead.',
        ]);

        if (! BangladeshPhoneNumberRule::isValidFormat(BangladeshPhoneNumberRule::cleanNumber($validated['phone']))) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid 11-digit Bangladesh mobile number (e.g. 017XXXXXXXX).',
            ]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')
            ->with('success', 'Your account is ready. Welcome!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }

    public function showForgotPassword(HoneypotTimingGuardService $timingGuard): Response
    {
        return Inertia::render('Storefront/Account/Auth/ForgotPassword', [
            'timingToken' => $timingGuard->generateTimingToken(),
        ]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ]);

        // Same response whether or not the email exists, so the form cannot be used to discover accounts.
        Password::sendResetLink(['email' => Str::lower(trim($validated['email']))]);

        return back()->with('success', 'If an account exists for that email, we have sent a password reset link.');
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        return Inertia::render('Storefront/Account/Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower(trim($validated['email'])),
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This password reset link is invalid or has expired. Please request a new one.',
            ]);
        }

        return redirect()->route('account.login')
            ->with('success', 'Your password has been changed. Please log in.');
    }
}
