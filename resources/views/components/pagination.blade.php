@props(['paginator'])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = collect([1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $last])
        ->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
    $link = 'inline-flex min-w-9 h-9 items-center justify-center rounded-lg px-2 text-sm font-semibold';
@endphp

<nav class="card px-4 py-3 flex flex-wrap items-center justify-between gap-3" aria-label="Sahifalar">
    <span class="text-sm muted">
        {{ number_format($paginator->firstItem(), 0, ',', ' ') }}–{{ number_format($paginator->lastItem(), 0, ',', ' ') }}
        / {{ number_format($paginator->total(), 0, ',', ' ') }}
    </span>
    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="{{ $link }} muted opacity-50">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="{{ $link }} hover:bg-[var(--surface-2)]" aria-label="Oldingi">‹</a>
        @endif

        @foreach ($pages as $i => $page)
            @if ($i > 0 && $page - $pages[$i - 1] > 1)
                <span class="{{ $link }} muted">…</span>
            @endif
            @if ($page === $current)
                <span class="{{ $link }} text-white" style="background: var(--accent-strong, #1c5cab)" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" class="{{ $link }} hover:bg-[var(--surface-2)]">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="{{ $link }} hover:bg-[var(--surface-2)]" aria-label="Keyingi">›</a>
        @else
            <span class="{{ $link }} muted opacity-50">›</span>
        @endif
    </div>
</nav>
