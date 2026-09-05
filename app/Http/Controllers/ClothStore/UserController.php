<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ClothStore\Role;
use App\Models\ClothStore\Permission;
use App\Models\ClothStore\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('csRoles')->get();
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all()->groupBy('module');
        $logs = ActivityLog::with('user')->orderByDesc('created_at')->take(50)->get();

        return view('cloth-store.users.index', compact('users', 'roles', 'permissions', 'logs'));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role_ids' => 'array'
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'staff', // Root role
                'is_active' => true
            ]);

            if ($request->has('role_ids')) {
                $user->csRoles()->sync($request->role_ids);
            }

            // Log activity
            ActivityLog::create([
                'user_id' => auth()->id() ?? $user->id,
                'action' => 'Created User',
                'module' => 'System',
                'details' => "Created user {$user->name}",
                'ip_address' => $request->ip()
            ]);

            DB::commit();
            return back()->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:cs_roles',
            'permissions' => 'array'
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $request->name,
                'description' => $request->description
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->sync($request->permissions);
            }

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'Created Role',
                'module' => 'System',
                'details' => "Created custom role {$role->name}",
                'ip_address' => $request->ip()
            ]);

            DB::commit();
            return back()->with('success', 'Role created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}
