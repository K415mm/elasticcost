<?php

namespace App\Http\Controllers;

use App\Events\UserUpdated;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    /**
     * Display the user management page (file-manager style layout).
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $roles = ['client', 'manager', 'sales_manager', 'partner', 'ceo'];

        $permissions = Permission::orderBy('sort_order')->orderBy('label')->get();

        $roleColors = [
            'client' => 'info',
            'manager' => 'primary',
            'sales_manager' => 'warning',
            'partner' => 'success',
            'ceo' => 'danger',
        ];

        return view('users.index', compact('users', 'roles', 'permissions', 'roleColors'));
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
        ]);

        broadcast(new UserUpdated($user))->toOthers();

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        $oldRole = $user->getOriginal('role');
        $user->role = $validated['role'];

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        // Flush permission cache if role changed
        if ($oldRole !== $validated['role']) {
            RolePermission::flushRoleCache($oldRole);
            RolePermission::flushRoleCache($validated['role']);
        }

        broadcast(new UserUpdated($user))->toOthers();

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
