<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class StaffUserController extends Controller
{
    /**
     * Display a listing of staff users with roles.
     */
    public function index(): Response
    {
        $staff = User::with('roles')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'created_at' => $user->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/Staff/StaffIndex', [
            'staff' => $staff,
        ]);
    }

    /**
     * Show the form for creating a new staff member.
     */
    public function create(): Response
    {
        $roles = Role::orderBy('name')->pluck('name');

        return Inertia::render('Admin/Staff/StaffForm', [
            'staff' => null,
            'availableRoles' => $roles,
            'assignedRoles' => [],
        ]);
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles($validated['roles']);

        return redirect()->route('admin.staff.index')
            ->with('success', "Staff user '{$user->name}' created successfully.");
    }

    /**
     * Show the form for editing an existing staff member.
     */
    public function edit(User $staff): Response
    {
        $roles = Role::orderBy('name')->pluck('name');

        return Inertia::render('Admin/Staff/StaffForm', [
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
            ],
            'availableRoles' => $roles,
            'assignedRoles' => $staff->roles->pluck('name')->toArray(),
        ]);
    }

    /**
     * Update an existing staff member.
     */
    public function update(Request $request, User $staff): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($staff->id)],
            'password' => ['nullable', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $staff->update($userData);
        $staff->syncRoles($validated['roles']);

        return redirect()->route('admin.staff.index')
            ->with('success', "Staff user '{$staff->name}' updated successfully.");
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(Request $request, User $staff): RedirectResponse
    {
        if ($staff->id === $request->user()?->id) {
            return redirect()->route('admin.staff.index')
                ->with('error', 'You cannot delete your own active account.');
        }

        if ($staff->hasRole('Super Admin') && User::role('Super Admin')->count() <= 1) {
            return redirect()->route('admin.staff.index')
                ->with('error', 'Cannot delete the only remaining Super Admin account.');
        }

        $staff->delete();

        return redirect()->route('admin.staff.index')
            ->with('success', "Staff user '{$staff->name}' removed successfully.");
    }
}

