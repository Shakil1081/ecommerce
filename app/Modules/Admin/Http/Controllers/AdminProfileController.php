<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Actions\LogAuditTrailAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Profile" for back-office staff: every staff member edits only their own name, email and
 * password, so these routes need no module permission (only auth + staff).
 */
final class AdminProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $this->staff($request);

        return Inertia::render('Admin/Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values(),
                'member_since' => $user->created_at?->format('d M Y'),
            ],
        ]);
    }

    public function update(Request $request, LogAuditTrailAction $audit): RedirectResponse
    {
        $user = $this->staff($request);

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $old = $user->only(['name', 'email']);
        $user->update($validated);

        $audit(
            event: 'auth.profile.updated',
            description: "Staff [{$user->name}] updated their profile",
            auditable: $user,
            oldValues: $old,
            newValues: $user->only(['name', 'email']),
        );

        return back()->with('success', 'Your profile has been updated.');
    }

    public function updatePassword(Request $request, LogAuditTrailAction $audit): RedirectResponse
    {
        $user = $this->staff($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'The new password must be different from the current one.',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        // Keep this browser signed in with a fresh session id after the credential change
        $request->session()->regenerate();

        $audit(
            event: 'auth.password.changed',
            description: "Staff [{$user->name}] changed their password",
            auditable: $user,
        );

        return back()->with('success', 'Your password has been changed.');
    }

    private function staff(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
