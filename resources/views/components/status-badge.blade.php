@props(['status'])

@php
    $color = match (true) {
        $status === null => '--series-other',
        str_contains($status, 'O‘qimoqda') => '--series-1',
        str_contains($status, 'Bitirgan') || str_contains($status, 'bitiruvchi') => '--series-3',
        str_contains($status, 'Chetlash') => '--series-2',
        default => '--series-other',
    };
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1.5 whitespace-nowrap') }}>
    <span class="swatch rounded-full" style="background: var({{ $color }})"></span>{{ $status ?? '—' }}
</span>
