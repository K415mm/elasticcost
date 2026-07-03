<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    /**
     * Display the permission matrix page.
     */
    public function index()
    {
        $permissions = Permission::orderBy('sort_order')->orderBy('label')->get();
        $roles = ['client', 'manager', 'sales_manager', 'partner', 'ceo'];

        $matrix = [];
        foreach ($permissions as $permission) {
            foreach ($roles as $role) {
                $rp = RolePermission::where('role', $role)
                    ->where('permission_id', $permission->id)
                    ->first();
                $matrix[$permission->key][$role] = $rp ? $rp->is_allowed : false;
            }
        }

        $roleColors = [
            'client' => 'info',
            'manager' => 'primary',
            'sales_manager' => 'warning',
            'partner' => 'success',
            'ceo' => 'danger',
        ];

        return view('roles.permissions', compact('permissions', 'roles', 'matrix', 'roleColors'));
    }

    /**
     * Update the permission matrix.
     */
    public function update(Request $request)
    {
        $permissions = Permission::all();
        $roles = ['client', 'manager', 'sales_manager', 'partner', 'ceo'];
        $toggles = $request->input('permissions', []);

        foreach ($permissions as $permission) {
            foreach ($roles as $role) {
                $isAllowed = isset($toggles[$permission->key][$role]);

                RolePermission::updateOrCreate(
                    [
                        'role' => $role,
                        'permission_id' => $permission->id,
                    ],
                    [
                        'is_allowed' => $isAllowed,
                    ]
                );
            }
        }

        return redirect()->route('roles.permissions')->with('success', 'Permission matrix updated successfully.');
    }
}
