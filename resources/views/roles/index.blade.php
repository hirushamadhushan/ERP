@extends('layouts.app')

@section('title', 'Roles')
@section('subtitle', 'Manage roles')

@section('content')
<div class="space-y-6">

    <!-- Card Container -->
    <div class="bg-white rounded-2xl border border-purple-100 p-6 shadow-sm">

        <!-- Card Header -->
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-bold text-slate-900">All roles</h2>
                <p class="text-xs text-slate-400">View, add and manage system user permissions and roles</p>
            </div>
            <button id="openAddRoleBtn" type="button"
                class="inline-flex items-center gap-2 px-4 py-2 text-white text-xs font-bold rounded-xl shadow-md transition-all duration-200 hover:scale-105 cursor-pointer"
                style="background:linear-gradient(135deg,#7c3aed,#4f46e5);box-shadow:0 4px 12px rgba(124,58,237,.35);">
                <i class="bi bi-plus-lg text-sm"></i> Add
            </button>
        </div>

        <!-- Yajra DataTable -->
        <div class="table-responsive">
            <table id="rolesTable" class="table w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th>Roles</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================== ADD ROLE MODAL ==================== -->
<div id="addRoleModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" style="display:none;">
    <div class="bg-white rounded-2xl border border-purple-100 shadow-2xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-purple-100" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="bi bi-shield-plus"></i> Add New Role
            </h3>
            <button type="button" class="closeModalBtn text-white/70 hover:text-white text-xl font-bold leading-none">&times;</button>
        </div>

        <form id="addRoleForm" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Role Name <span class="text-rose-500">*</span></label>
                <input type="text" name="name"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
                    placeholder="e.g. Cashier" required>
                <span class="text-xs text-rose-500 error-text add_name_error"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Description</label>
                <textarea name="description"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
                    rows="3" placeholder="Brief description of this role..."></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" class="closeModalBtn px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 text-white text-xs font-bold rounded-xl shadow-md transition-all hover:scale-105"
                    style="background:linear-gradient(135deg,#7c3aed,#4f46e5);box-shadow:0 4px 12px rgba(124,58,237,.35);">Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== EDIT ROLE MODAL WITH PERMISSIONS ==================== -->
<div id="editRoleModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 overflow-y-auto" style="display:none;">
    <div class="bg-white rounded-2xl border border-purple-100 shadow-2xl w-full max-w-2xl overflow-hidden my-4">

        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-purple-100" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="bi bi-shield-check"></i> Edit Role
            </h3>
            <button type="button" class="closeModalBtn text-white/70 hover:text-white text-xl font-bold leading-none">&times;</button>
        </div>

        <form id="editRoleForm" class="p-6">
            @csrf
            @method('PUT')
            <input type="hidden" id="edit_role_id" name="id">

            <!-- Role Name -->
            <div class="mb-5">
                <label class="block text-sm font-bold text-slate-700 mb-1">Role Name <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_role_name" name="name"
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
                    required>
                <span class="text-xs text-rose-500 error-text edit_name_error"></span>
            </div>

            <!-- Permissions Section -->
            <div class="mb-5">
                <label class="block text-sm font-bold text-slate-700 mb-3">Permissions:</label>

                <!-- ---- OTHERS GROUP ---- -->
                <div class="mb-4 border border-slate-200 rounded-xl overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                        <span class="text-sm font-bold text-slate-700">Others</span>
                        <label class="flex items-center gap-1.5 ml-auto cursor-pointer text-xs text-slate-500 font-semibold select-all-group" data-group="others">
                            <input type="checkbox" class="group-select-all w-3.5 h-3.5 accent-purple-600 cursor-pointer"> Select all
                        </label>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach([
                            'others.export_buttons' => 'View export to buttons (csv/excel/print/pdf) on tables',
                            'others.dashboard'      => 'View Dashboard',
                        ] as $perm => $label)
                        <label class="flex items-start gap-2.5 cursor-pointer group">
                            <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                                class="permission-check others-check mt-0.5 w-4 h-4 rounded accent-purple-600 cursor-pointer shrink-0">
                            <span class="text-xs text-slate-600 group-hover:text-slate-800 leading-relaxed">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- ---- USER GROUP ---- -->
                <div class="mb-4 border border-slate-200 rounded-xl overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                        <span class="text-sm font-bold text-slate-700">User</span>
                        <label class="flex items-center gap-1.5 ml-auto cursor-pointer text-xs text-slate-500 font-semibold" data-group="user">
                            <input type="checkbox" class="group-select-all w-3.5 h-3.5 accent-purple-600 cursor-pointer"> Select all
                        </label>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach([
                            'user.view'   => 'View user',
                            'user.add'    => 'Add user',
                            'user.edit'   => 'Edit user',
                            'user.delete' => 'Delete user',
                        ] as $perm => $label)
                        <label class="flex items-start gap-2.5 cursor-pointer group">
                            <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                                class="permission-check user-check mt-0.5 w-4 h-4 rounded accent-purple-600 cursor-pointer shrink-0">
                            <span class="text-xs text-slate-600 group-hover:text-slate-800">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- ---- ROLES GROUP ---- -->
                <div class="mb-4 border border-slate-200 rounded-xl overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                        <span class="text-sm font-bold text-slate-700">Roles</span>
                        <label class="flex items-center gap-1.5 ml-auto cursor-pointer text-xs text-slate-500 font-semibold" data-group="role">
                            <input type="checkbox" class="group-select-all w-3.5 h-3.5 accent-purple-600 cursor-pointer"> Select all
                        </label>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach([
                            'role.view'   => 'View role',
                            'role.add'    => 'Add Role',
                            'role.edit'   => 'Edit role',
                            'role.delete' => 'Delete role',
                        ] as $perm => $label)
                        <label class="flex items-start gap-2.5 cursor-pointer group">
                            <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                                class="permission-check role-check mt-0.5 w-4 h-4 rounded accent-purple-600 cursor-pointer shrink-0">
                            <span class="text-xs text-slate-600 group-hover:text-slate-800">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- ---- SYSTEM GROUP ---- -->
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                        <span class="text-sm font-bold text-slate-700">System</span>
                        <label class="flex items-center gap-1.5 ml-auto cursor-pointer text-xs text-slate-500 font-semibold" data-group="system">
                            <input type="checkbox" class="group-select-all w-3.5 h-3.5 accent-purple-600 cursor-pointer"> Select all
                        </label>
                    </div>
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach([
                            'system.settings'  => 'Access Settings',
                            'system.reports'   => 'View Reports',
                            'system.audit_log' => 'View Audit Log',
                        ] as $perm => $label)
                        <label class="flex items-start gap-2.5 cursor-pointer group">
                            <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                                class="permission-check system-check mt-0.5 w-4 h-4 rounded accent-purple-600 cursor-pointer shrink-0">
                            <span class="text-xs text-slate-600 group-hover:text-slate-800">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Form Footer -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" class="closeModalBtn px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 text-white text-xs font-bold rounded-xl shadow-md transition-all hover:scale-105"
                    style="background:linear-gradient(135deg,#7c3aed,#4f46e5);box-shadow:0 4px 12px rgba(124,58,237,.35);">Update Role</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    // ─── DataTable ────────────────────────────────────────────────────────────
    var table = $('#rolesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('roles.index') }}",
        columns: [
            { data: 'roles',  name: 'name' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        pageLength: 25,
        language: { search: 'Search:', lengthMenu: 'Show _MENU_ entries' }
    });

    // ─── Modal helpers ────────────────────────────────────────────────────────
    function openModal(id)  { $(id).css('display','flex'); }
    function closeAllModals(){ $('#addRoleModal,#editRoleModal').css('display','none'); }

    $('#openAddRoleBtn').click(function () {
        $('#addRoleForm')[0].reset();
        $('.add_name_error').text('');
        openModal('#addRoleModal');
    });

    $(document).on('click', '.closeModalBtn', closeAllModals);

    // Click outside modal backdrop to close
    $(document).on('click', '#addRoleModal, #editRoleModal', function (e) {
        if ($(e.target).is('#addRoleModal, #editRoleModal')) closeAllModals();
    });

    // ─── "Select all" checkboxes per group ───────────────────────────────────
    $(document).on('change', '.group-select-all', function () {
        var group   = $(this).closest('[data-group]').data('group') || $(this).closest('label').data('group');
        var checked = $(this).is(':checked');
        // find the wrapping permission block
        var $block = $(this).closest('.border.border-slate-200.rounded-xl');
        $block.find('.permission-check').prop('checked', checked);
    });

    // ─── Add Role ─────────────────────────────────────────────────────────────
    $('#addRoleForm').submit(function (e) {
        e.preventDefault();
        $('.add_name_error').text('');
        $.ajax({
            url: "{{ route('roles.store') }}", type: 'POST', data: $(this).serialize(),
            success: function (r) {
                if (r.status === 'success') { closeAllModals(); table.ajax.reload(); showToast(r.message, 'success'); }
            },
            error: function (xhr) {
                if (xhr.status === 422) $.each(xhr.responseJSON.errors, function (k, v) { $('.add_' + k + '_error').text(v[0]); });
            }
        });
    });

    // ─── Edit Role – load data ────────────────────────────────────────────────
    $(document).on('click', '.edit-role-btn', function () {
        var id = $(this).data('id');
        $('.edit_name_error').text('');
        // uncheck all
        $('#editRoleModal input[type=checkbox]').prop('checked', false);

        $.get('/roles/' + id + '/edit', function (role) {
            $('#edit_role_id').val(role.id);
            $('#edit_role_name').val(role.name);

            // tick saved permissions
            if (role.permissions && Array.isArray(role.permissions)) {
                role.permissions.forEach(function (perm) {
                    $('#editRoleModal input[name="permissions[]"][value="' + perm + '"]').prop('checked', true);
                });
            }
            openModal('#editRoleModal');
        });
    });

    // ─── Edit Role – submit ───────────────────────────────────────────────────
    $('#editRoleForm').submit(function (e) {
        e.preventDefault();
        var id = $('#edit_role_id').val();
        $('.edit_name_error').text('');
        $.ajax({
            url: '/roles/' + id, type: 'POST', data: $(this).serialize(),
            success: function (r) {
                if (r.status === 'success') { closeAllModals(); table.ajax.reload(); showToast(r.message, 'success'); }
            },
            error: function (xhr) {
                if (xhr.status === 422) $.each(xhr.responseJSON.errors, function (k, v) { $('.edit_' + k + '_error').text(v[0]); });
            }
        });
    });

    // ─── Delete Role ──────────────────────────────────────────────────────────
    $(document).on('click', '.delete-role-btn', function () {
        if (!confirm('Are you sure you want to delete this role?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: '/roles/' + id, type: 'DELETE',
            success: function (r) {
                if (r.status === 'success') { table.ajax.reload(); showToast(r.message, 'danger'); }
            }
        });
    });

    // ─── Toast helper ─────────────────────────────────────────────────────────
    function showToast(msg, type) {
        var color = type === 'success'
            ? 'linear-gradient(135deg,#7c3aed,#4f46e5)'
            : 'linear-gradient(135deg,#ef4444,#dc2626)';
        var $t = $('<div>').text(msg).css({
            position:'fixed', bottom:'24px', right:'24px', zIndex:9999,
            padding:'12px 20px', borderRadius:'14px', color:'#fff',
            fontWeight:700, fontSize:'0.8rem',
            background: color,
            boxShadow:'0 6px 20px rgba(0,0,0,.2)',
            transition:'opacity .4s'
        }).appendTo('body');
        setTimeout(function(){ $t.css('opacity',0); setTimeout(function(){ $t.remove(); }, 400); }, 2500);
    }

});
</script>
@endpush
