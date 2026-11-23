@props(['label', 'value', 'hint' => null, 'color' => null, 'href' => null])

<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['card px-5 py-4 min-w-0', 'block transition hover:-translate-y-0.5 hover:border-[var(--accent)] focus-visible:outline-2 focus-visible:outline-[var(--accent)]' => $href]) }}>
    <div class="flex items-center gap-2 text-xs font-semibold muted uppercase tracking-wide">
        @if ($color)
            <span class="swatch" style="background: var({{ $color }})"></span>
        @endif
        <span class="truncate">{{ $label }}</span>
    </div>
    <div class="mt-2 text-[28px] leading-none font-extrabold tracking-tight">{{ $value }}</div>
    @if ($hint)
        <div class="mt-2 text-xs secondary">{{ $hint }}</div>
    @endif
    @if ($href)
        <div class="mt-2 text-xs font-semibold" style="color: var(--accent)">Ro‘yxatni ochish →</div>
    @endif
</{{ $href ? 'a' : 'div' }}>
