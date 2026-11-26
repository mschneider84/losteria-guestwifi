@if ($paginator->hasPages())
<nav class="pager">
    @if ($paginator->onFirstPage())<span>‹</span>@else<a href="{{ $paginator->previousPageUrl() }}">‹</a>@endif
    <span>{{ __('Seite :p von :n', ['p' => $paginator->currentPage(), 'n' => $paginator->lastPage()]) }}</span>
    @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">›</a>@else<span>›</span>@endif
</nav>
@endif
