<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Team management for the Backup & Data panel.
 *
 * Every route here is admin-only. On top of that, an admin can never lock
 * themselves out: they cannot demote, disable or delete their own account, and
 * the last remaining active admin is always protected.
 */
class TeamController extends Controller
{
    /** The whole team, shaped the way the settings panel renders it. */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'team'    => $this->team(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::in(User::ROLES)],
            'title'    => ['nullable', 'string', 'max:100'],
            'phone'    => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // A blank password means "generate one and show it once".
        $generated = null;
        if (blank($validated['password'] ?? null)) {
            $generated = Str::password(12, true, true, false);
        }

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role'      => $validated['role'],
            'title'     => $validated['title'] ?? null,
            'phone'     => $validated['phone'] ?? null,
            'password'  => $generated ?? $validated['password'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLogger::log(
            'Team member added',
            sprintf('%s joined as %s', $user->name, $user->role_label),
            'system',
            $user,
            ['role' => $user->role],
            'created'
        );

        return response()->json([
            'success'  => true,
            'message'  => $user->name . ' added to the team.',
            'password' => $generated,
            'team'     => $this->team(),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'  => ['required', Rule::in(User::ROLES)],
            'title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($guard = $this->guardAdminDemotion($request, $user, $validated['role'])) {
            return $guard;
        }

        $user->update($validated);

        ActivityLogger::log(
            'Team member updated',
            sprintf('%s is now %s', $user->name, $user->role_label),
            'system',
            $user,
            $validated,
            'updated'
        );

        return response()->json([
            'success' => true,
            'message' => $user->name . ' updated.',
            'team'    => $this->team(),
        ]);
    }

    /** Enables or disables sign-in for one member. */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $target = $request->boolean('is_active');

        if (!$target) {
            if ($user->id === $request->user()->id) {
                return $this->refuse('You cannot disable your own account.');
            }

            if ($this->isLastActiveAdmin($user)) {
                return $this->refuse('At least one active administrator must remain.');
            }
        }

        $user->update(['is_active' => $target]);

        ActivityLogger::log(
            'Team access changed',
            sprintf('%s was %s', $user->name, $target ? 'enabled' : 'disabled'),
            'system',
            $user,
            ['is_active' => $target],
            'updated'
        );

        return response()->json([
            'success' => true,
            'message' => sprintf('%s %s.', $user->name, $target ? 'can sign in again' : 'can no longer sign in'),
            'team'    => $this->team(),
        ]);
    }

    /**
     * Issues a new password. Returned once, in the response, so the admin can
     * pass it on — it is never stored in readable form or logged.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $password = $validated['password'] ?? Str::password(12, true, true, false);

        $user->forceFill([
            'password'       => Hash::make($password),
            'remember_token' => null,
        ])->save();

        ActivityLogger::log(
            'Password reset',
            sprintf("%s's password was reset by an administrator", $user->name),
            'system',
            $user,
            [],
            'updated'
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Password reset for ' . $user->name . '.',
            'password' => $password,
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->refuse('You cannot remove your own account.');
        }

        if ($this->isLastActiveAdmin($user)) {
            return $this->refuse('At least one active administrator must remain.');
        }

        // Work this member has done stays in the record; only access is removed.
        $user->assignedOrders()->update(['tailor_id' => null]);

        $name = $user->name;
        $user->delete();

        ActivityLogger::log(
            'Team member removed',
            $name . ' was removed from the team',
            'system',
            null,
            [],
            'deleted'
        );

        return response()->json([
            'success' => true,
            'message' => $name . ' removed from the team.',
            'team'    => $this->team(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function team()
    {
        return User::query()
            ->orderByRaw("FIELD(role, 'admin', 'staff', 'tailor')")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'title', 'phone', 'is_active', 'last_login_at'])
            ->map(fn (User $u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role,
                'role_label' => $u->role_label,
                'title'      => $u->title,
                'phone'      => $u->phone,
                'is_active'  => (bool) $u->is_active,
                'last_login' => $u->last_login_at?->diffForHumans(),
                'initials'   => $u->initials,
            ]);
    }

    /** Blocks the change that would leave the shop with no way back in. */
    private function guardAdminDemotion(Request $request, User $user, string $newRole): ?JsonResponse
    {
        if ($user->role !== User::ROLE_ADMIN || $newRole === User::ROLE_ADMIN) {
            return null;
        }

        if ($user->id === $request->user()->id) {
            return $this->refuse('You cannot change your own role.');
        }

        if ($this->isLastActiveAdmin($user)) {
            return $this->refuse('At least one administrator must remain.');
        }

        return null;
    }

    private function isLastActiveAdmin(User $user): bool
    {
        if ($user->role !== User::ROLE_ADMIN) {
            return false;
        }

        return User::where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }

    private function refuse(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
