<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    /**
     * Display users list or return Yajra DataTables JSON response.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = User::select(['id', 'username', 'name', 'role', 'email', 'status'])->latest();

            return DataTables::of($data)
                ->addColumn('action', function ($row) {
                    $isAdmin = strtolower($row->role) === 'admin';

                    $btn = '<div class="flex items-center gap-2">';

                    // View â€” always visible
                    $btn .= '<button type="button" class="view-user-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-all duration-150 hover:scale-105" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);box-shadow:0 2px 8px rgba(14,165,233,.35);" data-id="'.$row->id.'"><i class="bi bi-eye-fill"></i> View</button>';

                    if ($isAdmin) {
                        // Admin row â€” locked badge instead of Edit/Delete
                        $btn .= '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-amber-700 bg-amber-100 border border-amber-200 cursor-not-allowed select-none" title="Admin account is protected"><i class="bi bi-shield-lock-fill"></i> Protected</span>';
                    } else {
                        // Normal users â€” show Edit & Delete
                        $btn .= '<button type="button" class="edit-user-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-all duration-150 hover:scale-105" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);box-shadow:0 2px 8px rgba(124,58,237,.35);" data-id="'.$row->id.'"><i class="bi bi-pencil-fill"></i> Edit</button>';
                        $btn .= '<button type="button" class="delete-user-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-all duration-150 hover:scale-105" style="background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 2px 8px rgba(239,68,68,.35);" data-id="'.$row->id.'"><i class="bi bi-trash-fill"></i> Delete</button>';
                    }

                    $btn .= '</div>';

                    return $btn;
                })
                ->editColumn('username', function ($row) {
                    return $row->username ?: 'N/A';
                })
                ->editColumn('role', function ($row) {
                    $isAdmin = strtolower($row->role) === 'admin';
                    if ($isAdmin) {
                        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-700 border border-purple-200"><i class="bi bi-shield-fill text-[10px]"></i> '.$row->role.'</span>';
                    }

                    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">'.$row->role.'</span>';
                })
                ->rawColumns(['action', 'role'])
                ->make(true);
        }

        $roles = Role::all();

        return view('users.index', compact('roles'));
    }

    /**
     * Store a newly created user via AJAX modal.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $validated['password'] = Hash::make($validated['password']);

        $user = $this->databaseTransaction(
            fn () => User::create($validated),
            'The username or email address is already in use.',
            'email'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully!',
            'user' => $user,
        ]);
    }

    /**
     * Display specific user details for View Modal.
     */
    public function show($id)
    {
        $user = User::findOrFail($id);

        return response()->json($user);
    }

    /**
     * Show edit form data for Edit Modal.
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);

        // Block editing Admin users
        if (strtolower($user->role) === 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin accounts cannot be edited.',
            ], 403);
        }

        return response()->json($user);
    }

    /**
     * Update user via AJAX modal.
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::findOrFail($id);

        // Server-side: block editing Admin users
        if (strtolower($user->role) === 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin accounts are protected and cannot be modified.',
            ], 403);
        }

        $validated = $request->validated();

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $this->databaseTransaction(
            fn () => $user->update($validated),
            'The username or email address is already in use.',
            'email'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully!',
            'user' => $user,
        ]);
    }

    /**
     * Delete user via AJAX.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Server-side: block deleting Admin users
        if (strtolower($user->role) === 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin accounts are protected and cannot be deleted.',
            ], 403);
        }

        $this->databaseTransaction(
            fn () => $user->delete(),
            'This user is assigned to existing records and cannot be deleted.'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully!',
        ]);
    }
}
