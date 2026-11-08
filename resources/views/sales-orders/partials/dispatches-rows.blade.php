@forelse($dispatches as $dispatch)
    @php
        $statusClass = match(strtolower($dispatch->status)) {
            'delivered' => 'status-delivered',
            'dispatched' => 'status-dispatched',
            default => 'status-pending',
        };
    @endphp
    <tr data-dispatch-id="{{ $dispatch->id }}">
        <td class="font-bold">{{ $dispatch->dispatch_number }}</td>
        <td>{{ $dispatch->order_number ?: '' }}</td>
        <td>{{ $dispatch->invoice_number ?: '' }}</td>
        <td>{{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('Y-m-d') : '' }}</td>
        <td>
            <span class="dispatch-status {{ $statusClass }}">
                {{ $dispatch->status }}
            </span>
        </td>
        <td>
            <button type="button" class="btn-dispatch-view" data-id="{{ $dispatch->id }}">
                View
            </button>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" style="text-align: center; padding: 36px; color: #64748b;">
            No dispatches found matching your search.
        </td>
    </tr>
@endforelse
