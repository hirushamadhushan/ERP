<style>
.serial-card{background:white;border:1px solid #eee3ff;border-top:3px solid #7c3aed;border-radius:4px;padding:18px;margin-bottom:18px;min-width:0;box-shadow:0 2px 8px rgba(0,0,0,0.04)}
.serial-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr));gap:14px}
.serial-card label{display:block;font-size:12px;font-weight:700;margin-bottom:4px;color:#1e293b}
.serial-card input:not([type=checkbox]),.serial-card select{width:100%;border:1px solid #cbd5e1;border-radius:4px;padding:5px 10px;background:white;min-width:0;height:32px;font-size:12px}
.serial-btn{display:inline-flex;align-items:center;justify-content:center;gap:0.35rem;border-radius:4px;padding:6px 14px;background:#7c3aed;color:white;font-size:12px;font-weight:700;height:32px;border:none;cursor:pointer;transition:all 0.15s ease;text-decoration:none}
.serial-btn:hover{background:#6d28d9;color:white}
.serial-secondary{background:#1e1b4b;color:white}
.serial-secondary:hover{background:#0f172a;color:white}
.serial-card h2{font-weight:700;margin-bottom:14px;color:#0f172a}
.serial-card hr{margin:18px 0;border-color:#e2e8f0}
.serial-table{width:100%;text-align:left;border-collapse:collapse;font-size:12px}
.serial-table td,.serial-table th{padding:8px 10px;border-bottom:1px solid #e2e8f0}
.serial-table th{background:#1e1b4b;color:#ffffff;font-size:11px;font-weight:700;text-transform:uppercase;border-right:1px solid rgba(255,255,255,0.2)}
.serial-table th:last-child{border-right:none}
@media(max-width:480px){.serial-card{padding:16px}}
@media print{aside,header,nav,.no-print{display:none!important}main{margin:0!important;padding:0!important}.serial-card{border:0;box-shadow:none}body{background:white!important}}
</style>
@unless($hideFlashAlerts ?? false)
@if(session('success'))<p id="delivery-success-alert" role="status" class="p-4 mb-4 bg-emerald-50 text-emerald-800 rounded-xl transition-opacity duration-500" style="opacity:1">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert" class="p-4 mb-4 bg-rose-50 text-rose-800 rounded-xl">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@endunless
@if(session('success'))
@push('scripts')
<script>
AppPage.ready(() => {
    const alert = document.getElementById('delivery-success-alert');
    if (!alert) return;
    const timer = window.setTimeout(() => {
        alert.style.opacity = '0';
        window.setTimeout(() => alert.remove(), 500);
    }, 5000);
    AppPage.signal.addEventListener('abort', () => window.clearTimeout(timer), {once:true});
});
</script>
@endpush
@endif
