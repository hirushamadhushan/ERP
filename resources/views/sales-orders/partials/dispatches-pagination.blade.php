<div class="entries-info">
    @if($dispatches->total() > 0)
        Showing {{ $dispatches->firstItem() }} to {{ $dispatches->lastItem() }} of {{ $dispatches->total() }} entries
    @else
        Showing 0 to 0 of 0 entries
    @endif
</div>

@if($dispatches->hasPages())
    <div class="pagination-controls">
        {{-- Previous Page Link --}}
        @if ($dispatches->onFirstPage())
            <span class="page-btn disabled">Previous</span>
        @else
            <a href="{{ $dispatches->previousPageUrl() }}" class="page-btn">Previous</a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($dispatches->getUrlRange(max(1, $dispatches->currentPage() - 2), min($dispatches->lastPage(), $dispatches->currentPage() + 2)) as $page => $url)
            @if ($page == $dispatches->currentPage())
                <span class="page-btn active">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
            @endif
        @endforeach

        @if($dispatches->lastPage() > $dispatches->currentPage() + 2)
            <span class="page-ellipsis">...</span>
            <a href="{{ $dispatches->url($dispatches->lastPage()) }}" class="page-btn">{{ $dispatches->lastPage() }}</a>
        @endif

        {{-- Next Page Link --}}
        @if ($dispatches->hasMorePages())
            <a href="{{ $dispatches->nextPageUrl() }}" class="page-btn">Next</a>
        @else
            <span class="page-btn disabled">Next</span>
        @endif
    </div>
@endif
