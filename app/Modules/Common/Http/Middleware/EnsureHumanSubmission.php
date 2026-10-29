<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use App\Modules\Common\Security\HoneypotTimingGuardService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureHumanSubmission
{
    public function __construct(
        private readonly HoneypotTimingGuardService $guardService
    ) {}

    /**
     * Handle an incoming request and ensure it passes bot honeypot and timing checks.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        float $minSeconds = HoneypotTimingGuardService::DEFAULT_MINIMUM_SECONDS,
        string $honeypotField = HoneypotTimingGuardService::DEFAULT_HONEYPOT_FIELD
    ): Response {
        $result = $this->guardService->inspect($request, (float) $minSeconds, $honeypotField);

        if ($result['is_bot']) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Automated bot activity detected.',
                    'errors' => [
                        'bot_protection' => [$result['reason']],
                    ],
                ], 422);
            }

            return back()->withErrors([
                'bot_protection' => $result['reason'],
            ]);
        }

        return $next($request);
    }
}
