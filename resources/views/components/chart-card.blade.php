@props(['id', 'title', 'subtitle' => null, 'height' => 320])

<section {{ $attributes->merge(['class' => 'card p-5 flex flex-col min-w-0']) }}>
    <header class="flex items-start justify-between gap-3 mb-3">
        <div class="min-w-0">
            <h3 class="text-[15px] font-bold tracking-tight">{{ $title }}</h3>
            @if ($subtitle)
                <p class="text-xs muted mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" class="btn-ghost shrink-0" data-table-toggle="{{ $id }}" aria-pressed="false">Jadval</button>
    </header>
    <div id="chart-{{ $id }}" data-chart="{{ $id }}" style="min-height: {{ $height }}px" class="-mx-2"></div>
    <div id="table-{{ $id }}" class="hidden overflow-auto" style="max-height: {{ $height }}px"></div>
    {{ $slot }}
</section>
