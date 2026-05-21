@props(['paginator', 'label' => 'registros', 'perPageOptions' => [5, 10, 25, 50], 'defaultPerPage' => 5])

@if($paginator->total() > 0)
<nav class="pagination-bar" aria-label="Paginacao">
    {{-- Info row: counter + per page --}}
    <div class="pagination-bar__info">
        <span class="pagination-bar__counter">
            <span class="pagination-bar__counter-range">{{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}</span>
            <span class="pagination-bar__counter-sep">de</span>
            <span class="pagination-bar__counter-total">{{ number_format($paginator->total(), 0, ',', '.') }}</span>
            <span class="pagination-bar__counter-label">{{ $label }}</span>
        </span>

        <select
            onchange="window.location.href=this.value"
            class="pagination-bar__per-page"
            aria-label="Registros por pagina"
        >
            @foreach($perPageOptions as $size)
                <option
                    value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}"
                    {{ request('per_page', $defaultPerPage) == $size ? 'selected' : '' }}
                >{{ $size }} / pag</option>
            @endforeach
        </select>
    </div>

    {{-- Navigation row --}}
    @if($paginator->hasPages())
    <div class="pagination-bar__nav">
        {{-- Previous --}}
        @if($paginator->onFirstPage())
            <span class="pagination-bar__btn pagination-bar__btn--disabled" aria-disabled="true">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="pagination-bar__btn" rel="prev" aria-label="Anterior">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
        @endif

        {{-- Page numbers --}}
        <div class="pagination-bar__pages">
            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();

                // Smart window: show first, last, and pages around current
                $pages = collect();

                // Always show page 1
                $pages->push(1);

                // Pages around current
                for ($i = max(2, $currentPage - 1); $i <= min($lastPage - 1, $currentPage + 1); $i++) {
                    $pages->push($i);
                }

                // Always show last page
                if ($lastPage > 1) {
                    $pages->push($lastPage);
                }

                $pages = $pages->unique()->sort()->values();
            @endphp

            @foreach($pages as $index => $page)
                @if($index > 0 && $page - $pages[$index - 1] > 1)
                    <span class="pagination-bar__ellipsis">&hellip;</span>
                @endif

                @if($page == $currentPage)
                    <span class="pagination-bar__page pagination-bar__page--active" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="pagination-bar__page">{{ $page }}</a>
                @endif
            @endforeach
        </div>

        {{-- Next --}}
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="pagination-bar__btn" rel="next" aria-label="Proxima">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        @else
            <span class="pagination-bar__btn pagination-bar__btn--disabled" aria-disabled="true">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
        @endif
    </div>
    @endif
</nav>
@endif
