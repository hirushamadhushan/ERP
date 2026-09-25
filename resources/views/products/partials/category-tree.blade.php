@php($childCounts = $records->groupBy('parent_id')->map->count())
<div class="category-browser">
    <div class="category-toolbar">
        <div><h2>Category directory <span>{{ $records->count() }}</span></h2><p>{{ $records->whereNull('parent_id')->count() }} main categories <span aria-hidden="true">&middot;</span> {{ $records->whereNotNull('parent_id')->count() }} sub-categories</p></div>
        <div class="category-tools">
            <label class="category-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="category-search" placeholder="Find a category..." aria-label="Search categories"></label>
            <button type="button" id="expand-categories" class="category-tool" title="Expand all categories"><i class="bi bi-arrows-expand" aria-hidden="true"></i> Expand all</button>
            <button type="button" id="collapse-categories" class="category-tool" title="Collapse all categories"><i class="bi bi-arrows-collapse" aria-hidden="true"></i> Collapse all</button>
        </div>
    </div>
    <div class="category-scroll">
    <table id="reference-table" class="category-directory">
        <thead><tr><th>Category hierarchy</th><th>Code</th><th>Description</th><th class="category-actions-heading">Actions</th></tr></thead>
        <tbody>
        @foreach($records as $record)
            @php($children = $childCounts->get($record->id, 0))
            <tr data-category-id="{{ $record->id }}" data-parent-id="{{ $record->parent_id }}" data-search="{{ strtolower($record->name.' '.$record->code.' '.$record->description) }}" class="{{ $record->parent_id ? 'category-child-row' : 'category-root-row' }}">
                <td class="category-tree-cell"><svg class="category-lines" aria-hidden="true"></svg><div class="category-node" style="--depth:{{ $record->tree_depth }}">
                    @if($children)<button type="button" class="tree-toggle category-toggle" aria-expanded="true" aria-label="Toggle {{ $record->name }}"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>@else<span class="category-toggle-placeholder" aria-hidden="true"></span>@endif
                    <span class="category-folder {{ $record->parent_id ? 'category-folder-child' : '' }}"><i class="bi {{ $record->parent_id ? 'bi-folder2' : 'bi-folder2-open' }}" aria-hidden="true"></i></span>
                    <div class="category-name"><strong>{{ $record->name }}</strong><small>{{ $record->parent_id ? 'Level '.$record->tree_depth : 'Main category' }}@if($children) &middot; {{ $children }} {{ $children === 1 ? 'child' : 'children' }}@endif</small></div>
                    @if($record->tree_depth < \App\Models\Category::MAX_DEPTH)<button type="button" class="add-child category-add" data-parent="{{ $record->id }}" aria-label="Add sub-category to {{ $record->name }}" title="Add sub-category"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>@endif
                </div></td>
                <td>@if($record->code)<span class="category-code">{{ $record->code }}</span>@else<span class="category-muted">&mdash;</span>@endif</td>
                <td><p class="category-description" title="{{ $record->description }}">{{ $record->description ?: 'No description' }}</p></td>
                <td><div class="category-row-actions">
                    <button type="button" class="edit-reference category-icon-button" aria-label="Edit {{ $record->name }}" title="Edit category" data-record="{{ json_encode($record->only(['id','name','code','description','parent_id'])) }}" data-url="{{ route($prefix.'.update', $record) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i></button>
                    <form class="delete-reference" method="POST" action="{{ route($prefix.'.destroy', $record) }}">@csrf @method('DELETE')<button type="submit" class="category-icon-button category-delete" aria-label="Delete {{ $record->name }}" title="Delete category"><i class="bi bi-trash3" aria-hidden="true"></i></button></form>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <div id="category-empty" class="category-empty" @if($records->isNotEmpty()) hidden @endif><i class="bi bi-folder2-open" aria-hidden="true"></i><h3>{{ $records->isEmpty() ? 'Build your category directory' : 'No matching categories' }}</h3><p>{{ $records->isEmpty() ? 'Start with a main category, then use + to organise its sub-categories.' : 'Try another name, code or description.' }}</p></div>
    <div class="category-footer"><span><i class="bi bi-diagram-3" aria-hidden="true"></i> Main category + 5 sub-category levels</span><span>Use <strong>+</strong> beside a category to add a child</span></div>
</div>
@push('scripts')
<style>
.category-toolbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap}.category-toolbar h2{font-size:16px;font-weight:700;color:#1e293b;margin:0}.category-toolbar h2 span{display:inline-block;margin-left:6px;padding:2px 8px;background:#f3e8ff;color:#7e22ce;border-radius:20px;font-size:11px}.category-toolbar p{margin:5px 0 0;color:#64748b;font-size:12px}.category-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.category-search{display:flex;align-items:center;gap:8px;padding:8px 11px;border:1px solid #e2e8f0;border-radius:9px;color:#94a3b8}.category-search input{width:170px;border:0!important;outline:0;background:transparent;font-size:12px;padding:0!important;box-shadow:none!important}.category-tool{padding:8px 10px;color:#64748b;border:1px solid #e2e8f0;border-radius:8px;font-size:11px;display:flex;gap:6px;align-items:center}.category-tool:hover{color:#7e22ce;background:#faf5ff;border-color:#e9d5ff}.category-scroll{overflow-x:auto;border:1px solid #ede9f5;border-radius:12px}.category-directory{width:100%;border-collapse:collapse;text-align:left;min-width:720px}.category-directory th{font-size:10px;text-transform:uppercase;letter-spacing:.07em;background:#f8fafc;color:#64748b;font-weight:600}.category-directory td,.category-directory th{padding:12px 14px!important}.category-directory td{border-bottom:1px solid #f1f5f9;vertical-align:middle}.category-directory tbody tr:last-child td{border-bottom:0}.category-root-row{background:#fcfaff}.category-directory tbody tr:hover{background:#faf5ff}.category-node{position:relative;display:flex;align-items:center;gap:9px;padding-left:calc(var(--depth)*24px);min-height:38px}.category-connector{position:absolute;left:calc(var(--depth)*24px - 14px);top:-18px;bottom:50%;width:13px;border-left:1px solid #ddd6ee;border-bottom:1px solid #ddd6ee;border-bottom-left-radius:5px}.category-toggle,.category-toggle-placeholder{width:22px;flex:0 0 22px}.category-toggle{height:24px;border-radius:5px;font-size:11px;color:#7e22ce}.category-toggle:hover{background:#f3e8ff}.category-folder{width:32px;height:32px;display:grid;place-items:center;background:#f3e8ff;color:#9333ea;border:1px solid #e9d5ff;border-radius:9px;flex-shrink:0}.category-folder-child{background:#fff;color:#94a3b8;border-color:#e2e8f0}.category-name{min-width:95px}.category-name strong{display:block;font-size:13px;font-weight:600;color:#334155;overflow-wrap:anywhere}.category-name small{display:block;font-size:10px;color:#94a3b8;margin-top:2px}.category-add{display:grid;place-items:center;flex:0 0 25px;width:25px;height:25px;border:1px dashed #d8b4fe;border-radius:7px;color:#9333ea;background:white;margin-left:5px;font-size:11px}.category-add:hover{background:#9333ea;color:white;border-style:solid}.category-code{font-family:monospace;font-size:11px;color:#64748b;background:#f1f5f9;border-radius:5px;padding:4px 7px;white-space:nowrap}.category-description{font-size:12px;color:#94a3b8;margin:0;max-width:240px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.category-muted{color:#cbd5e1}.category-row-actions{display:flex;justify-content:flex-end;gap:5px}.category-actions-heading{text-align:right}.category-icon-button{width:30px;height:30px;border:1px solid #e2e8f0;background:white;border-radius:7px;color:#64748b;display:grid;place-items:center;font-size:13px}.category-icon-button:hover{background:#faf5ff;color:#9333ea;border-color:#d8b4fe}.category-delete:hover{background:#fff1f2;color:#e11d48;border-color:#fecdd3}.category-footer{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;padding-top:15px;color:#94a3b8;font-size:11px}.category-footer i{color:#a855f7;margin-right:6px}.category-empty{text-align:center;padding:45px 20px;color:#94a3b8}.category-empty[hidden]{display:none}.category-empty>i{font-size:32px;color:#c084fc}.category-empty h3{font-size:15px;color:#475569;margin:12px 0 6px;font-weight:600}.category-empty p{font-size:12px}.category-browser button:focus-visible{outline:2px solid #a855f7;outline-offset:3px}.category-search:focus-within{border-color:#a855f7;box-shadow:0 0 0 3px #faf5ff}
@media(max-width:640px){.category-tools{width:100%}.category-search{width:100%}.category-search input{width:100%}.category-footer{font-size:10px}}
</style>
<style>
/* Draw connectors across the full cell, including padding between rows. */
.category-directory .category-tree-cell{position:relative}
.category-lines{position:absolute;inset:0;width:100%;height:100%;overflow:visible;pointer-events:none}
.category-lines path{fill:none;stroke:#b8a0d2;stroke-width:1.25;vector-effect:non-scaling-stroke}
.category-directory .category-node{min-height:30px;gap:8px}
.category-directory td{padding-top:7px!important;padding-bottom:7px!important}
.category-directory .category-toggle{position:relative;z-index:1;background:#fff;border:1px solid #c4b5db;border-radius:5px;height:18px;width:18px;flex-basis:18px;margin:0 2px;font-size:0}
.category-directory .category-toggle::before{content:'';position:absolute;width:8px;height:1.5px;background:#7e22ce;left:4px;top:7px}
.category-directory .category-toggle[aria-expanded="false"]::after{content:'';position:absolute;height:8px;width:1.5px;background:#7e22ce;left:7px;top:4px}
.category-directory .category-toggle i{display:none}
.category-directory .category-folder{width:26px;height:26px;border-radius:6px}
.category-directory .category-toggle-placeholder{position:relative}
.category-directory .category-toggle-placeholder::after{content:'';position:absolute;width:5px;height:5px;border-radius:50%;background:#b8a0d2;left:9px;top:-2px}
.category-directory .category-name small{color:#64748b}
</style>
@endpush
