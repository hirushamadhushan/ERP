@extends('layouts.app')

@section('title', 'View User')

@section('content')
@php
    $profile = $user->profile;
    $initials = collect(preg_split('/\s+/', trim($user->name ?: $user->username ?: 'U')))->filter()->map(fn ($word) => mb_substr($word, 0, 1))->take(2)->join('');
    $isActive = $user->status === 'active';
    $value = fn ($field) => filled(data_get($profile, $field)) ? data_get($profile, $field) : '—';
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">View User</h1>
            <p class="mt-1 text-sm text-slate-500">Profile, documents and activity history.</p>
        </div>
        <label class="block w-full sm:w-80">
            <span class="sr-only">Switch user</span>
            <select id="user-switcher" class="w-full rounded-xl border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                @foreach($switchUsers as $switchUser)
                    <option value="{{ route('users.view', $switchUser) }}" @selected($switchUser->id === $user->id)>{{ $switchUser->name ?: $switchUser->username }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
        <aside class="h-fit rounded-2xl border border-purple-100 border-t-2 border-t-purple-500 bg-white p-6 text-center shadow-sm">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full border-4 border-purple-100 bg-purple-50 text-3xl font-semibold text-purple-700 ring-2 ring-white">{{ $initials ?: 'U' }}</div>
            <h2 class="mt-5 text-lg font-bold text-slate-900">{{ $user->name ?: $user->username }}</h2>
            <p class="mt-1 text-sm font-medium text-purple-600">{{ $user->assignedRole?->name ?? 'Unassigned' }}</p>
            <dl class="mt-5 divide-y divide-slate-100 border-y border-slate-100 text-left text-sm">
                <div class="py-3"><dt class="font-bold text-slate-700">Username</dt><dd class="mt-1 break-all text-purple-600">{{ $user->username ?: '—' }}</dd></div>
                <div class="py-3"><dt class="font-bold text-slate-700">Email</dt><dd class="mt-1 break-all text-purple-600">{{ $user->email }}</dd></div>
                <div class="flex items-center justify-between py-3"><dt class="font-bold text-slate-700">Status</dt><dd><span class="rounded-full px-2 py-1 text-xs font-bold {{ $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $isActive ? 'Active' : 'Inactive' }}</span></dd></div>
            </dl>
            <button id="open-user-editor" data-url="{{ route('users.edit', $user) }}" type="button" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-purple-700"><i class="bi bi-pencil-square"></i> Edit User</button>
        </aside>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="grid grid-cols-3 border-b border-slate-200">
                <button type="button" data-user-tab="information" class="user-tab border-t-4 border-purple-600 bg-purple-50 px-3 py-4 text-sm font-bold text-purple-700 sm:text-base"><i class="bi bi-person-fill mr-1"></i>User Information</button>
                <button type="button" data-user-tab="documents" class="user-tab border-t-4 border-transparent px-3 py-4 text-sm font-bold text-slate-600 hover:bg-slate-50 sm:text-base"><i class="bi bi-paperclip mr-1"></i>Documents &amp; Notes</button>
                <button type="button" data-user-tab="activities" class="user-tab border-t-4 border-transparent px-3 py-4 text-sm font-bold text-slate-600 hover:bg-slate-50 sm:text-base"><i class="bi bi-journal-check mr-1"></i>Activities</button>
            </div>

            <div data-user-panel="information" class="p-6">
                <div class="grid gap-3 border-b border-slate-200 pb-5 text-sm sm:grid-cols-2">
                    <p><span class="font-bold text-slate-800">Sales Commission Percentage (%):</span> <span class="text-purple-700">{{ number_format((float) $user->commission_percent, 2) }}%</span></p>
                    <p><span class="font-bold text-slate-800">Allowed Contacts:</span> <span class="text-purple-700">{{ $user->restrict_contacts ? ($user->selectedContacts->pluck('name')->filter()->join(', ') ?: 'None selected') : 'All' }}</span></p>
                </div>
                <h3 class="mt-5 text-lg font-bold text-purple-700">More Information</h3>
                <div class="mt-3 grid gap-x-8 gap-y-3 text-sm md:grid-cols-3">
                    @foreach(['date_of_birth' => 'Date of birth', 'gender' => 'Gender', 'marital_status' => 'Marital Status', 'blood_group' => 'Blood Group', 'mobile' => 'Mobile Number', 'alternate_contact' => 'Alternate contact number', 'family_contact' => 'Family contact number', 'facebook_link' => 'Facebook Link', 'twitter_link' => 'Twitter Link', 'social_media_1' => 'Social Media 1', 'social_media_2' => 'Social Media 2', 'custom_field_1' => 'Custom field 1', 'custom_field_2' => 'Custom field 2', 'custom_field_3' => 'Custom field 3', 'custom_field_4' => 'Custom field 4', 'guardian_name' => 'Guardian Name', 'id_proof_name' => 'ID proof name', 'id_proof_number' => 'ID proof number'] as $field => $label)
                        <p><span class="font-bold text-slate-800">{{ $label }}:</span> <span class="text-slate-600">{{ $value($field) }}</span></p>
                    @endforeach
                </div>
                <div class="mt-6 grid gap-5 border-y border-slate-200 py-5 text-sm md:grid-cols-2"><p><span class="font-bold text-slate-800">Permanent Address:</span><br><span class="text-slate-600">{{ $value('permanent_address') }}</span></p><p><span class="font-bold text-slate-800">Current Address:</span><br><span class="text-slate-600">{{ $value('current_address') }}</span></p></div>
                <h3 class="mt-5 text-lg font-bold text-purple-700">Bank Details</h3>
                <div class="mt-3 grid gap-x-8 gap-y-3 text-sm md:grid-cols-3">
                    @foreach(['account_holder_name' => "Account Holder's Name", 'account_number' => 'Account Number', 'bank_name' => 'Bank Name', 'bank_identifier_code' => 'Bank Identifier Code', 'bank_branch' => 'Branch', 'tax_payer_id' => 'Tax Payer ID'] as $field => $label)
                        <p><span class="font-bold text-slate-800">{{ $label }}:</span> <span class="text-slate-600">{{ $value($field) }}</span></p>
                    @endforeach
                </div>
            </div>

            <div data-user-panel="documents" class="hidden p-6">
                <div id="profile-message" class="mb-4 hidden rounded-lg px-4 py-3 text-sm font-semibold"></div>
                <div class="flex justify-end"><button id="open-note-modal" type="button" class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-purple-700"><i class="bi bi-plus-lg"></i> Add</button></div>
                <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">Action</th><th class="px-4 py-3">Heading</th><th class="px-4 py-3">Added By</th><th class="px-4 py-3">Created At</th><th class="px-4 py-3">Updated At</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($user->notes as $note)<tr class="hover:bg-purple-50/40"><td class="px-4 py-3"><span class="inline-flex rounded-lg bg-purple-100 px-2.5 py-1 text-xs font-bold text-purple-700">{{ $note->is_private ? 'Private' : 'View' }}</span></td><td class="px-4 py-3 font-semibold text-slate-800">{{ $note->heading ?: 'Note' }}</td><td class="px-4 py-3 text-slate-600">{{ $note->creator?->name ?? 'System' }}</td><td class="px-4 py-3 text-slate-600">{{ $note->created_at->format('d/m/Y H:i') }}</td><td class="px-4 py-3 text-slate-600">{{ $note->updated_at->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No data available in table</td></tr>@endforelse</tbody></table></div>
                <h3 class="mt-6 font-bold text-slate-800">Documents</h3><div class="mt-3 overflow-x-auto rounded-xl border border-slate-200"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">Document</th><th class="px-4 py-3">Added By</th><th class="px-4 py-3">Created At</th><th class="px-4 py-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($user->documents as $document)<tr><td class="px-4 py-3 font-medium text-slate-800">{{ $document->name }}</td><td class="px-4 py-3 text-slate-600">{{ $document->creator?->name ?? 'System' }}</td><td class="px-4 py-3 text-slate-600">{{ $document->created_at->format('d/m/Y H:i') }}</td><td class="px-4 py-3"><a class="font-bold text-purple-700 hover:text-purple-900" href="{{ route('users.documents.download', $document) }}"><i class="bi bi-download"></i> Download</a></td></tr>@empty<tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No documents available</td></tr>@endforelse</tbody></table></div>
            </div>

            <div data-user-panel="activities" class="hidden overflow-x-auto p-6"><table class="min-w-full text-left text-sm"><thead class="border-b border-slate-200 text-xs uppercase text-slate-500"><tr><th class="pb-3 pr-4">Date</th><th class="pb-3 pr-4">Action</th><th class="pb-3 pr-4">By</th><th class="pb-3">Note</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($user->activities as $activity)<tr><td class="py-3 pr-4 text-slate-600">{{ $activity->created_at->format('d/m/Y H:i') }}</td><td class="py-3 pr-4 font-semibold text-purple-700">{{ $activity->action }}</td><td class="py-3 pr-4 text-slate-700">{{ $activity->actor?->name ?? 'System' }}</td><td class="py-3 text-slate-600">{{ $activity->note ?: '—' }}</td></tr>@empty<tr><td colspan="4" class="py-8 text-center text-slate-500">No activity recorded for this user.</td></tr>@endforelse</tbody></table></div>
        </section>
    </div>
</div>
<div id="ajax-user-editor" class="fixed inset-0 z-50 hidden bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5"><div class="mx-auto flex h-full w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-slate-50 shadow-2xl"><div class="flex shrink-0 items-center justify-between border-b border-purple-100 bg-white px-5 py-3.5"><div><h2 class="text-base font-bold text-slate-900"><i class="bi bi-pencil-square mr-2 text-purple-600"></i>Edit User</h2><p class="mt-0.5 text-[11px] text-slate-400">Update the account, role and access rules</p></div><button type="button" data-close-user-editor class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-xl text-slate-500 hover:bg-rose-50 hover:text-rose-600">&times;</button></div><div id="ajax-user-editor-body" class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5"></div></div></div>
<div id="note-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4">
    <form id="user-note-form" action="{{ route('users.notes.store', $user) }}" enctype="multipart/form-data" class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4"><h2 class="text-lg font-bold text-slate-900">Add Note</h2><button type="button" data-close-note class="text-xl text-slate-400 hover:text-slate-700">&times;</button></div>
        <div class="space-y-5 p-6"><div><label class="block text-sm font-bold text-slate-900">Heading:*</label><input name="heading" required maxlength="255" placeholder="Enter note heading" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-100"></div><div><label class="block text-sm font-bold text-slate-900">Description:*</label><textarea name="body" required maxlength="5000" rows="8" placeholder="Write note details..." class="mt-2 block w-full resize-y rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-100"></textarea></div><div><label class="block text-sm font-bold text-slate-900">Documents:</label><label id="document-drop-zone" class="mt-2 flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-purple-300 bg-purple-50 text-sm font-medium text-slate-600 hover:border-purple-500"><i class="bi bi-cloud-arrow-up text-3xl text-purple-600"></i><span class="mt-2">Drop files here to upload</span><span class="mt-1 text-xs">or click to select files</span><input id="note-documents" name="documents[]" type="file" multiple class="hidden"></label><p id="selected-files" class="mt-2 text-xs text-slate-600"></p></div><label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800"><input type="hidden" name="is_private" value="0"><input name="is_private" value="1" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500"> Is Private?</label></div>
        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4"><button type="button" data-close-note class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Close</button><button class="rounded-xl bg-purple-600 px-5 py-2 text-sm font-bold text-white hover:bg-purple-700">Save</button></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('turbo:load', () => {
    const editor = document.querySelector('#ajax-user-editor');
    const closeEditor = () => { editor.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };
    document.querySelector('#open-user-editor')?.addEventListener('click', async (event) => {
        editor.classList.remove('hidden'); document.body.classList.add('overflow-hidden');
        const body = document.querySelector('#ajax-user-editor-body'); body.innerHTML = '<div class="flex min-h-72 items-center justify-center"><span class="h-9 w-9 animate-spin rounded-full border-4 border-purple-100 border-t-purple-600"></span></div>';
        try {
            const response = await fetch(event.currentTarget.dataset.url + '?form=1'); if (!response.ok) throw new Error('Unable to load the Edit User form.');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html'); const form = page.querySelector('#create-user-form'); if (!form) throw new Error('Edit User form is unavailable.');
            form.classList.remove('mx-auto'); body.replaceChildren(document.importNode(form, true));
            const formScript = [...page.querySelectorAll('script')].find(script => script.textContent.includes('create-user-form'));
            if (formScript) { const script = document.createElement('script'); script.textContent = formScript.textContent; document.body.appendChild(script); script.remove(); }
        } catch (error) { body.innerHTML = '<p class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700"></p>'; body.firstElementChild.textContent = error.message; }
    });
    document.querySelectorAll('[data-close-user-editor]').forEach(button => button.addEventListener('click', closeEditor));
    editor?.addEventListener('click', event => { if (event.target === editor) closeEditor(); });
    const modal = document.querySelector('#note-modal');
    document.querySelector('#open-note-modal')?.addEventListener('click', () => modal.classList.replace('hidden', 'flex'));
    document.querySelectorAll('[data-close-note]').forEach(button => button.addEventListener('click', () => modal.classList.replace('flex', 'hidden')));
    document.querySelector('#note-documents')?.addEventListener('change', event => { document.querySelector('#selected-files').textContent = [...event.target.files].map(file => file.name).join(', '); });
    document.querySelector('#user-switcher')?.addEventListener('change', (event) => Turbo.visit(event.target.value));
    document.querySelectorAll('.user-tab').forEach((tab) => tab.addEventListener('click', () => {
        document.querySelectorAll('.user-tab').forEach((item) => item.className = 'user-tab border-t-4 border-transparent px-3 py-4 text-sm font-bold text-slate-600 hover:bg-slate-50 sm:text-base');
        tab.className = 'user-tab border-t-4 border-purple-600 bg-purple-50 px-3 py-4 text-sm font-bold text-purple-700 sm:text-base';
        document.querySelectorAll('[data-user-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.userPanel !== tab.dataset.userTab));
    }));
    document.querySelectorAll('#user-document-form, #user-note-form').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault(); const message = document.querySelector('#profile-message');
        const response = await fetch(form.action, {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'}, body: new FormData(form)});
        const result = await response.json(); message.textContent = result.message || 'Unable to save the change.'; message.className = 'mb-4 rounded-lg px-4 py-3 text-sm font-semibold '+(response.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700');
        if (response.ok) setTimeout(() => Turbo.visit(window.location.href), 450);
    }));
});
</script>
@endpush
