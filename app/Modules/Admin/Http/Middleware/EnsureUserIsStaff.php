<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the back-office to staff accounts.
 *
 * Storefront customers may also hold a `users` row (order history, wishlist, etc.),
 * so being authenticated is not enough: the user must hold at least one Spatie role.
 * Fine-grained module access is then enforced per route via `permission:` middleware.
 */
final class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('login'));
        }

        if ($user->roles()->doesntExist()) {
            abort(403, 'This account does not have back-office access.');
        }

        return $next($request);
    }
}
