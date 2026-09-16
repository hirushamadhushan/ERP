<style>
.serial-card{background:white;border:1px solid #eee3ff;border-radius:16px;padding:24px;margin-bottom:24px;min-width:0}
.serial-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr));gap:20px}
.serial-card label{display:block;font-size:13px;font-weight:600;margin-bottom:7px}
.serial-card input:not([type=checkbox]),.serial-card select{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:10px;background:white;min-width:0}
.serial-btn{display:inline-block;border-radius:12px;padding:10px 16px;background:#9333ea;color:white;font-size:13px;font-weight:600}
.serial-secondary{background:#f1f5f9;color:#334155}
.serial-card h2{font-weight:600;margin-bottom:20px}
.serial-card hr{margin:24px 0;border-color:#e2e8f0}
.serial-table{width:100%;text-align:left;border-collapse:collapse;font-size:13px}
.serial-table td,.serial-table th{padding:12px;border-bottom:1px solid #e2e8f0}
.serial-table th{background:#f8fafc}
@media(max-width:480px){.serial-card{padding:16px}}
@media print{aside,header,nav,.no-print{display:none!important}main{margin:0!important;padding:0!important}.serial-card{border:0;box-shadow:none}body{background:white!important}}
</style>
@if(session('success'))<p role="status" class="p-4 mb-4 bg-emerald-50 text-emerald-800 rounded-xl">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert" class="p-4 mb-4 bg-rose-50 text-rose-800 rounded-xl">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

