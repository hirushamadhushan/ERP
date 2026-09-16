@extends('layouts.app')

@section('title', 'Users')
@section('subtitle', 'Manage users')

@section('content')
<div class="space-y-6">

    <!-- Card Container -->
    <div class="bg-white rounded-2xl border border-purple-100 p-6 shadow-sm">

        <!-- Card Header -->
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-bold text-slate-900">All users</h2>
                <p class="text-xs text-slate-400">View, add, edit and manage system user accounts</p>
            </div>
            <button id="openAddUserBtn" type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-md shadow-purple-500/20 transition-all duration-200 cursor-pointer">
                <i class="bi bi-plus-lg text-sm"></i> Add
            </button>
        </div>

        <!-- Yajra DataTable -->
        <div class="sticky-table-host">
            <table id="usersTable" class="table w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>

</div>

<!-- ==================== ADD USER IN-PAGE AJAX MODAL ==================== -->
<div id="addUserModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl border border-purple-100 shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 bg-purple-50 border-b border-purple-100">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-person-plus-fill text-purple-600"></i> Add New User
            </h3>
            <button type="button" class="closeModalBtn text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <!-- Form -->
        <form id="addUserForm" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Username</label>
                <input type="text" name="username" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600" placeholder="e.g. admin1" required>
                <span class="text-xs text-rose-500 error-text username_error"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                <input type="text" name="name" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600" placeholder="e.g. Mr Sithum" required>
                <span class="text-xs text-rose-500 error-text name_error"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address</label>
                <input type="email" name="email" autocapitalize="none" pattern="[a-z0-9.!#$%&amp;'*+/=?^_`{|}~-]+@[a-z0-9.-]+\.[a-z]{2,}" title="Use a valid email address with lowercase letters only." class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600" placeholder="e.g. info.sithum@gmail.com" required>
                <span class="text-xs text-rose-500 error-text email_error"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="add_password"
                        class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600" placeholder="••••••••" required>
                    <button type="button" onclick="togglePwd('add_password', this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-purple-600 transition">
                        <i class="bi bi-eye-fill text-sm"></i>
                    </button>
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Password will be securely hashed before saving.</p>
                <span class="text-xs text-rose-500 error-text password_error"></span>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Role</label>
                    <select name="role" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600">
                        @foreach($roles->unique('name') as $r)
                            <option value="{{ $r->name }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-rose-500 error-text role_error"></span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600">
                        <option value="active">Active</option>
                        <option value="offline">Offline</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <span class="text-xs text-rose-500 error-text status_error"></span>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" class="closeModalBtn px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-md shadow-purple-500/20">Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== EDIT USER IN-PAGE AJAX MODAL ==================== -->
<div id="editUserModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl border border-purple-100 shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
        <div class="flex items-center justify-between px-6 py-4 bg-purple-50 border-b border-purple-100">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-pencil-square text-purple-600"></i> Edit User
            </h3>
            <button type="button" class="closeModalBtn text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form id="editUserForm" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit_user_id" name="id">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Username</label>
                <input type="text" id="edit_username" name="username" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600" required>
                <span class="text-xs text-rose-500 error-text username_error"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                <input type="text" id="edit_name" name="name" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600" required>
                <span class="text-xs text-rose-500 error-text name_error"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address</label>
                <input type="email" id="edit_email" name="email" autocapitalize="none" pattern="[a-z0-9.!#$%&amp;'*+/=?^_`{|}~-]+@[a-z0-9.-]+\.[a-z]{2,}" title="Use a valid email address with lowercase letters only." class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600" required>
                <span class="text-xs text-rose-500 error-text email_error"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password <span class="text-slate-400 font-normal lowercase">(leave blank to keep current)</span></label>
                <div class="relative">
                    <input type="password" name="password" id="edit_password"
                        class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600" placeholder="••••••••">
                    <button type="button" onclick="togglePwd('edit_password', this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-purple-600 transition">
                        <i class="bi bi-eye-fill text-sm"></i>
                    </button>
                </div>
                <p class="text-[10px] text-slate-400 mt-1">New password will be securely hashed before saving.</p>
                <span class="text-xs text-rose-500 error-text password_error"></span>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Role</label>
                    <select id="edit_role" name="role" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600">
                        @foreach($roles->unique('name') as $r)
                            <option value="{{ $r->name }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status</label>
                    <select id="edit_status" name="status" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-purple-600">
                        <option value="active">Active</option>
                        <option value="offline">Offline</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" class="closeModalBtn px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-md shadow-purple-500/20">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== VIEW USER IN-PAGE AJAX MODAL ==================== -->
<div id="viewUserModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
    <div class="bg-white rounded-2xl border border-purple-100 shadow-2xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 bg-purple-50 border-b border-purple-100">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-person-badge text-purple-600"></i> User Details
            </h3>
            <button type="button" class="closeModalBtn text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <div class="p-6 space-y-3">
            <div class="flex justify-between border-b pb-2">
                <span class="text-xs font-bold text-slate-400">Username:</span>
                <span id="view_username" class="text-xs font-semibold text-slate-800"></span>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-xs font-bold text-slate-400">Name:</span>
                <span id="view_name" class="text-xs font-semibold text-slate-800"></span>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-xs font-bold text-slate-400">Email:</span>
                <span id="view_email" class="text-xs font-semibold text-slate-800"></span>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-xs font-bold text-slate-400">Role:</span>
                <span id="view_role" class="text-xs font-semibold text-purple-600"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-xs font-bold text-slate-400">Status:</span>
                <span id="view_status" class="text-xs font-semibold"></span>
            </div>
        </div>

        <div class="flex justify-end p-4 bg-slate-50 border-t border-slate-100">
            <button type="button" class="closeModalBtn px-4 py-2 text-xs font-bold bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Password show/hide toggle
function togglePwd(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash-fill text-sm';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye-fill text-sm';
    }
}

$(document).ready(function() {

    // Setup CSRF header for Ajax
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Yajra DataTable with Export Buttons matching Photo 2
    var table = $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('users.index') }}",
        columns: [
            { data: 'username', name: 'username' },
            { data: 'name', name: 'name' },
            { data: 'role', name: 'role' },
            { data: 'email', name: 'email' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        dom: '<"flex flex-wrap items-center justify-between gap-4 mb-4"Bf>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"lip>',
        buttons: [
            { extend: 'csv', text: '<i class="bi bi-filetype-csv"></i> Export to CSV' },
            { extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Export to Excel' },
            { extend: 'print', text: '<i class="bi bi-printer"></i> Print' },
            { extend: 'colvis', text: '<i class="bi bi-eye-slash"></i> Column visibility' },
            { extend: 'pdf', text: '<i class="bi bi-file-earmark-pdf"></i> Export to PDF' }
        ],
        pageLength: 25,
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries"
        }
    });
    StickyDataTables.install(table);

    // Toast helper
    function showToast(msg, type) {
        var color = (type === 'success')
            ? 'linear-gradient(135deg,#7c3aed,#4f46e5)'
            : (type === 'warning')
                ? 'linear-gradient(135deg,#f59e0b,#d97706)'
                : 'linear-gradient(135deg,#ef4444,#dc2626)';
        var icon = (type === 'success') ? 'bi-check-circle-fill' : (type === 'warning') ? 'bi-shield-lock-fill' : 'bi-x-circle-fill';
        var $t = $('<div>').html('<i class="bi ' + icon + '"></i> ' + msg).css({
            position:'fixed', bottom:'24px', right:'24px', zIndex:9999,
            padding:'12px 20px', borderRadius:'14px', color:'#fff',
            fontWeight:700, fontSize:'0.8rem', display:'flex', alignItems:'center', gap:'8px',
            background: color, boxShadow:'0 6px 20px rgba(0,0,0,.2)', transition:'opacity .4s'
        }).appendTo('body');
        setTimeout(function(){ $t.css('opacity',0); setTimeout(function(){ $t.remove(); },400); }, 3000);
    }

    // Open Add User Modal
    $('#openAddUserBtn').click(function() {
        $('#addUserForm')[0].reset();
        $('.error-text').text('');
        $('#addUserModal').removeClass('hidden');
    });

    // Close Modals
    $('.closeModalBtn').click(function() {
        $('#addUserModal, #editUserModal, #viewUserModal').addClass('hidden');
    });

    // Submit Add User Form via AJAX
    $('#addUserForm').submit(function(e) {
        e.preventDefault();
        $('.error-text').text('');

        $.ajax({
            url: "{{ route('users.store') }}",
            type: "POST",
            data: $(this).serialize(),
            success: function(response) {
                if(response.status === 'success') {
                    $('#addUserModal').addClass('hidden');
                    $('#addUserForm')[0].reset();
                    table.ajax.reload();
                    showToast(response.message, 'success');
                }
            },
            error: function(xhr) {
                if(xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, val) {
                        $('.' + key + '_error').text(val[0]);
                    });
                } else {
                    showToast(AppErrors.message(xhr.status, xhr.responseJSON), 'danger');
                }
            }
        });
    });

    // View User Details via AJAX
    $(document).on('click', '.view-user-btn', function() {
        var id = $(this).data('id');
        $.get("/users/" + id, function(user) {
            $('#view_username').text(user.username || 'N/A');
            $('#view_name').text(user.name);
            $('#view_email').text(user.email);
            $('#view_role').text(user.role);
            $('#view_status').text(user.status);
            $('#viewUserModal').removeClass('hidden');
        });
    });

    // Edit User via AJAX
    $(document).on('click', '.edit-user-btn', function() {
        var id = $(this).data('id');
        $('.error-text').text('');
        $.ajax({
            url: '/users/' + id + '/edit',
            type: 'GET',
            success: function(user) {
                $('#edit_user_id').val(user.id);
                $('#edit_username').val(user.username);
                $('#edit_name').val(user.name);
                $('#edit_email').val(user.email);
                $('#edit_role').val(user.role);
                $('#edit_status').val(user.status);
                $('#editUserModal').removeClass('hidden');
            },
            error: function(xhr) {
                if(xhr.status === 403) {
                    showToast(xhr.responseJSON.message, 'warning');
                } else {
                    showToast(AppErrors.message(xhr.status, xhr.responseJSON), 'danger');
                }
            }
        });
    });

    // Submit Edit User Form via AJAX
    $('#editUserForm').submit(function(e) {
        e.preventDefault();
        var id = $('#edit_user_id').val();
        $('.error-text').text('');

        $.ajax({
            url: "/users/" + id,
            type: "POST",
            data: $(this).serialize(),
            success: function(response) {
                if(response.status === 'success') {
                    $('#editUserModal').addClass('hidden');
                    table.ajax.reload();
                    showToast(response.message, 'success');
                }
            },
            error: function(xhr) {
                if(xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, val) {
                        $('.' + key + '_error').text(val[0]);
                    });
                } else if(xhr.status === 403) {
                    $('#editUserModal').addClass('hidden');
                    showToast(xhr.responseJSON.message, 'warning');
                } else {
                    showToast(AppErrors.message(xhr.status, xhr.responseJSON), 'danger');
                }
            }
        });
    });

    // Delete User via AJAX
    $(document).on('click', '.delete-user-btn', function() {
        var id = $(this).data('id');
        if(!confirm('Are you sure you want to delete this user?')) return;
        $.ajax({
            url: '/users/' + id,
            type: 'DELETE',
            success: function(response) {
                if(response.status === 'success') {
                    table.ajax.reload();
                    showToast(response.message, 'success');
                }
            },
            error: function(xhr) {
                if(xhr.status === 403) {
                    showToast(xhr.responseJSON.message, 'warning');
                } else {
                    showToast(AppErrors.message(xhr.status, xhr.responseJSON), 'danger');
                }
            }
        });
    });

});
</script>
@endpush
