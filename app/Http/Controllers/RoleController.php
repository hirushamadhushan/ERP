<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
    /**
     * Display roles list or return Yajra DataTables JSON response.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Role::select(['id', 'name', 'description'])->latest();

            return DataTables::of($data)
                ->addColumn('roles', function ($row) {
                    return '
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: linear-gradient(135deg,#7c3aed,#4f46e5);">
                                <i class="bi bi-shield-fill text-white text-xs"></i>
                            </div>
                            <span class="font-semibold text-slate-800">'.e($row->name).'</span>
                        </div>';
                })
                ->addColumn('action', function ($row) {
                    $isSystemRole = strtolower($row->name) === 'admin';

                    $btn = '<div class="flex items-center gap-2">';
                    $btn .= '<button type="button" class="edit-role-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-all duration-150 hover:scale-105" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);box-shadow:0 2px 8px rgba(124,58,237,.35);" data-id="'.$row->id.'"><i class="bi bi-pencil-fill"></i> Edit</button>';

                    if ($isSystemRole) {
                        // Admin role â€” no delete allowed
                        $btn .= '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-amber-700 bg-amber-100 border border-amber-200 cursor-not-allowed select-none" title="System role cannot be deleted"><i class="bi bi-shield-lock-fill"></i> Protected</span>';
                    } else {
                        $btn .= '<button type="button" class="delete-role-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-all duration-150 hover:scale-105" style="background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 2px 8px rgba(239,68,68,.35);" data-id="'.$row->id.'"><i class="bi bi-trash-fill"></i> Delete</button>';
                    }

                    $btn .= '</div>';

                    return $btn;
                })
                ->rawColumns(['roles', 'action'])
                ->make(true);
        }

        return view('roles.index');
    }

    /**
     * Store a newly created role via AJAX.
     */
    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        $role = $this->databaseTransaction(
            fn () => Role::create($validated),
            'A role with this name already exists.',
            'name'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully!',
            'role' => $role,
        ]);
    }

    /**
     * Show role for editing (AJAX).
     */
    public function edit($id)
    {
        $role = Role::findOrFail($id);

        return response()->json($role);
    }

    /**
     * Update role via AJAX (handles permissions JSON).
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->roleData();
        $permissions = $validated['permissions'];
        unset($validated['permissions']);

        $this->databaseTransaction(
            function () use ($role, $validated, $permissions) { $role->update($validated); $role->syncPermissions($permissions); },
            'A role with this name already exists.',
            'name'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully!',
            'role' => $role,
        ]);
    }

    /**
     * Delete role via AJAX.
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Block deletion of the system Admin role
        if (strtolower($role->name) === 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'The Admin role is a system role and cannot be deleted.',
            ], 403);
        }

        $this->databaseTransaction(
            fn () => $role->delete(),
            'This role is assigned to one or more users and cannot be deleted.'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Role deleted successfully!',
        ]);
    }
}
