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
        {extend:'csvHtml5', text:'<i class="bi bi-download"></i> Export CSV', title, exportOptions:exportOptions(columns)},
        {extend:'excelHtml5', text:'<i class="bi bi-file-earmark-excel"></i> Export Excel', title, exportOptions:exportOptions(columns)},
        {extend:'print', text:'<i class="bi bi-printer"></i> Print', title, exportOptions:exportOptions(columns)},
        {extend:'colvis', text:'<i class="bi bi-layout-three-columns"></i> Column Visibility <i class="bi bi-chevron-down text-[10px] ml-0.5"></i>'},
        {extend:'pdfHtml5', text:'<i class="bi bi-file-earmark-pdf"></i> Export PDF', title, orientation:'landscape', exportOptions:exportOptions(columns)},
    ];
    tables.forEach(config => {
        const table = document.getElementById(config.id);
        if (!table || $.fn.dataTable.isDataTable(table)) return;
        const searching = config.searching !== undefined ? config.searching : false;
        const api = $(table).DataTable({
            dom: '<"delivery-table-toolbar flex flex-wrap items-center justify-between gap-3 mb-2"B>rt',
            buttons: exportButtons(config.exportColumns, config.title),
            paging: false,
            info: false,
            searching: searching,
            order: config.order || [],
            autoWidth: false,
            columnDefs: config.nonOrderable?.length ? [{orderable:false, targets:config.nonOrderable}] : [],
            language: {search:'Search this page:', emptyTable:config.emptyTable || 'No records available'},
        });
        window.StickyDataTables?.install(api);
    });
});
</script>
<style>
.delivery-table-toolbar { margin-bottom: 0.75rem !important; }
.delivery-table-toolbar .dt-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; float: none !important; }
.delivery-table-toolbar .dt-buttons button.dt-button {
    font-size: 12px !important;
    padding: 6px 13px !important;
    border-radius: 9px !important;
    border: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    color: #475569 !important;
    font-weight: 600 !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    line-height: 1.4 !important;
    transition: all .15s ease !important;
}
.delivery-table-toolbar .dt-buttons button.dt-button:hover {
    background: #f8fafc !important;
    color: #1e1b4b !important;
    border-color: #cbd5e1 !important;
}
.delivery-table-toolbar .dataTables_filter { display: none !important; }
</style>
@endpush
