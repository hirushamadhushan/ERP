<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    /**
     * Display users list or return Yajra DataTables JSON response.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = User::with('assignedRole')->select(['id', 'username', 'name', 'role_id', 'email', 'status'])->latest();

            return DataTables::of($data)
                ->filter(function ($query) use ($request) {
                    $search = trim((string) $request->input('search.value'));
                    if ($search === '') {
                        return;
                    }

                    $query->where(function ($userQuery) use ($search) {
                        $like = '%'.$search.'%';
                        $userQuery->where('users.username', 'like', $like)
                            ->orWhere('users.name', 'like', $like)
                            ->orWhere('users.email', 'like', $like)
                            ->orWhereHas('assignedRole', fn ($roleQuery) => $roleQuery->where('name', 'like', $like));
                    });
                })
                ->addColumn('action', function ($row) {
                    $isAdmin = strtolower($row->role) === 'admin';

                    $btn = '<div class="flex items-center gap-2">';

                    // View â€” always visible
                    $btn .= '<button type="button" class="view-full-user-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-all duration-150 hover:scale-105" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);box-shadow:0 2px 8px rgba(124,58,237,.35);" data-url="'.route('users.view', $row->id).'"><i class="bi bi-eye-fill"></i> View</button>';

                    if ($isAdmin) {
                        // Admin row â€” locked badge instead of Edit/Delete
                        $btn .= '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-amber-700 bg-amber-100 border border-amber-200 cursor-not-allowed select-none" title="Admin account is protected"><i class="bi bi-shield-lock-fill"></i> Protected</span>';
                    } else {
                        // Normal users â€” show Edit & Delete
                        $btn .= '<button type="button" class="edit-full-user-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white transition-all duration-150 hover:scale-105" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);box-shadow:0 2px 8px rgba(124,58,237,.35);" data-url="'.route('users.edit', $row->id).'"><i class="bi bi-pencil-fill"></i> Edit</button>';
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

    /** Display the full user and access-control form. */
    public function create()
    {
        return view('users.create', [
            'roles' => Role::orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'contacts' => Contact::where('status', 'active')->orderBy('name')->get(['id', 'name', 'contact_id', 'type']),
        ]);
    }

    /**
     * Store a newly created user via AJAX modal.
     */
    public function store(StoreUserRequest $request, UserManagementService $users)
    {
        $user = $users->create($request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('users.index')->with('success', 'User created successfully.');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully!',
            'redirect' => route('users.index'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->assignedRole?->name,
            ],
        ]);
    }

    /**
     * Display specific user details for View Modal.
     */
    public function show($id)
    {
        $user = User::with(['assignedRole', 'profile', 'locations', 'selectedContacts'])->findOrFail($id);

        return response()->json($user);
    }

    /** Render the complete user profile and its related records. */
    public function view(User $user)
    {
        $user->load(['assignedRole', 'profile', 'locations', 'selectedContacts', 'documents.creator', 'notes.creator', 'activities.actor']);
        $switchUsers = User::orderBy('name')->get(['id', 'name', 'username']);

        return view('users.view', compact('user', 'switchUsers'));
    }

    public function storeDocument(Request $request, User $user)
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);
        $file = $validated['document'];
        $path = $file->store('user-documents/'.$user->id);
        $document = $user->documents()->create([
            'name' => $file->getClientOriginalName(), 'path' => $path,
            'mime_type' => $file->getClientMimeType(), 'size' => $file->getSize(),
            'created_by' => $request->user()?->id,
        ]);
        $user->activities()->create([
            'actor_id' => $request->user()?->id, 'action' => 'Document added', 'note' => $document->name,
        ]);

        return response()->json(['message' => 'Document added successfully.']);
    }

    public function storeNote(Request $request, User $user)
    {
        $validated = $request->validate([
            'heading' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'is_private' => ['nullable', 'boolean'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);
        $user->notes()->create([
            'heading' => $validated['heading'], 'body' => $validated['body'],
            'is_private' => $request->boolean('is_private'), 'created_by' => $request->user()?->id,
        ]);
        foreach ($request->file('documents', []) as $file) {
            $user->documents()->create([
                'name' => $file->getClientOriginalName(), 'path' => $file->store('user-documents/'.$user->id),
                'mime_type' => $file->getClientMimeType(), 'size' => $file->getSize(), 'created_by' => $request->user()?->id,
            ]);
        }
        $user->activities()->create([
            'actor_id' => $request->user()?->id, 'action' => 'Note added', 'note' => $validated['heading'],
        ]);

        return response()->json(['message' => 'Note added successfully.']);
    }

    public function downloadDocument(UserDocument $document)
    {
        abort_unless(Storage::exists($document->path), 404);

        return Storage::download($document->path, $document->name);
    }

    /**
     * Show edit form data for Edit Modal.
     */
    public function edit(Request $request, $id)
    {
        $user = User::with(['profile', 'locations', 'selectedContacts'])->findOrFail($id);

        // Block editing Admin users
        if (strtolower($user->role) === 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin accounts cannot be edited.',
            ], 403);
        }

        if ($request->boolean('form')) {
            return view('users.create', [
                'user' => $user,
                'roles' => Role::orderBy('name')->get(),
                'locations' => Location::where('is_active', true)->orderBy('name')->get(),
                'contacts' => Contact::where('status', 'active')->orderBy('name')->get(['id', 'name', 'contact_id', 'type']),
            ]);
        }

        if ($request->ajax()) {
            return response()->json($user);
        }

        return view('users.create', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'contacts' => Contact::where('status', 'active')->orderBy('name')->get(['id', 'name', 'contact_id', 'type']),
        ]);
    }

    /** Update the full Add User form when it is opened in edit mode. */
    public function updateProfile(UpdateManagedUserRequest $request, User $user, UserManagementService $users)
    {
        $users->update($user, $request->validated());

        return response()->json([
            'message' => 'User updated successfully.',
            'redirect' => route('users.view', $user),
        ]);
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
        $validated['role_id'] = Role::where('name', $validated['role'])->value('id');
        unset($validated['role']);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $this->databaseTransaction(
            function () use ($user, $validated) {
                $user->update($validated);
                $user->activities()->create([
                    'actor_id' => auth()->id(),
                    'action' => 'User account updated',
                    'note' => 'Account details were updated from User Management.',
                ]);
            },
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
