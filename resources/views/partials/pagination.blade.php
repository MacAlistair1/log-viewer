@php
    $__current = $result['current_page'];
    $__last    = $result['last_page'];
    $__delta   = 2;

    $__rangeStart = max(2, $__current - $__delta);
    $__rangeEnd   = min($__last - 1, $__current + $__delta);

    $__pages = [1];
    if ($__rangeStart > 2) $__pages[] = '…';
    for ($__p = $__rangeStart; $__p <= $__rangeEnd; $__p++) $__pages[] = $__p;
    if ($__rangeEnd < $__last - 1) $__pages[] = '…';
    if ($__last > 1) $__pages[] = $__last;
    $__pages = array_values(array_unique($__pages, SORT_REGULAR));
@endphp

<div class="lv-footer">
    <span class="lv-footer-meta">
        {{ number_format($result['total']) }} {{ $result['total'] === 1 ? 'entry' : 'entries' }}
        &middot; page {{ $__current }} of {{ number_format($__last) }}
    </span>

    <nav class="lv-pagination" aria-label="Pagination">
        <a class="lv-page-btn lv-page-nav {{ $__current <= 1 ? 'disabled' : '' }}"
           @if ($__current <= 1) aria-disabled="true" tabindex="-1" @else href="{{ request()->fullUrlWithQuery(['page' => $__current - 1]) }}" @endif
           aria-label="Previous page">‹</a>

        @foreach ($__pages as $__p)
            @if ($__p === '…')
                <span class="lv-page-ellipsis">…</span>
            @else
                <a class="lv-page-btn {{ $__p === $__current ? 'is-current' : '' }}"
                   @if ($__p === $__current) aria-current="page" @else href="{{ request()->fullUrlWithQuery(['page' => $__p]) }}" @endif>
                    {{ $__p }}
                </a>
            @endif
        @endforeach

        <a class="lv-page-btn lv-page-nav {{ $__current >= $__last ? 'disabled' : '' }}"
           @if ($__current >= $__last) aria-disabled="true" tabindex="-1" @else href="{{ request()->fullUrlWithQuery(['page' => $__current + 1]) }}" @endif
           aria-label="Next page">›</a>
    </nav>
</div>
