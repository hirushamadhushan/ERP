@push('scripts')
<script>
AppPage.ready(() => {
    if (!window.jQuery?.fn?.DataTable) return;
    const tables = @json($deliveryTables);
    const exportOptions = columns => ({columns, format:{body:data => {
        const text = $('<div>').html(data).text().trim();
        return /^[=+\-@\t\r]/.test(text) ? "'" + text : text;
    }}});
    const exportButtons = (columns, title) => [
        {extend:'csvHtml5', text:'<i class="bi bi-filetype-csv"></i> Export to CSV', title, exportOptions:exportOptions(columns)},
        {extend:'excelHtml5', text:'<i class="bi bi-file-earmark-excel"></i> Export to Excel', title, exportOptions:exportOptions(columns)},
        {extend:'print', text:'<i class="bi bi-printer"></i> Print', title, exportOptions:exportOptions(columns)},
        {extend:'colvis', text:'<i class="bi bi-layout-three-columns"></i> Column visibility'},
        {extend:'pdfHtml5', text:'<i class="bi bi-file-earmark-pdf"></i> Export to PDF', title, orientation:'landscape', exportOptions:exportOptions(columns)},
    ];
    tables.forEach(config => {
        const table = document.getElementById(config.id);
        if (!table || $.fn.dataTable.isDataTable(table)) return;
        const api = $(table).DataTable({
            dom:'<"delivery-table-toolbar flex flex-wrap items-center justify-between gap-4 mb-5"Bf>rt',
            buttons:exportButtons(config.exportColumns, config.title), paging:false, info:false,
            searching:true, order:config.order || [], autoWidth:false,
            columnDefs:config.nonOrderable?.length ? [{orderable:false, targets:config.nonOrderable}] : [],
            language:{search:'Search this page:', emptyTable:config.emptyTable || 'No records available'},
        });
        window.StickyDataTables?.install(api);
    });
});
</script>
<style>
.delivery-table-toolbar .dt-buttons{display:flex;flex-wrap:wrap;gap:.25rem;float:none!important}
.delivery-table-toolbar .dataTables_filter{float:none!important;margin:0 0 0 auto!important;text-align:left!important;color:#0f172a!important;font-size:14px!important;white-space:nowrap}
.delivery-table-toolbar .dataTables_filter input{width:165px!important;height:34px!important;min-width:0!important;margin-left:.35rem!important;padding:.4rem .7rem!important;border:1px solid #cbd5e1!important;border-radius:.6rem!important;background:#fff!important;box-shadow:none!important;outline:none!important}
.delivery-table-toolbar .dataTables_filter input:focus{border-color:#9333ea!important;box-shadow:0 0 0 2px rgb(147 51 234 / .12)!important}
@media(max-width:640px){.delivery-table-toolbar{align-items:stretch}.delivery-table-toolbar .dataTables_filter{width:100%}.delivery-table-toolbar .dataTables_filter input{width:calc(100% - 7rem)!important}}
</style>
@endpush
