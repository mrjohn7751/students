@php
    $k = $stats['kpi'];
    $n = fn ($v) => $v === null ? '—' : number_format($v, 0, ',', ' ');
    $pct = fn ($part, $whole) => $whole > 0 ? round($part / $whole * 100, 1).'%' : '—';
    $canViewPersonalData = auth()->user()->can('view-personal-data');
    $studentsLink = fn (string $status) => $canViewPersonalData ? route('students.index', ['status' => $status] + array_filter($filters)) : null;
@endphp
<x-layout title="Statistika">
    <x-slot:actions>
        @php $studentSync = collect($stats['sync'])->firstWhere('entity', 'students'); @endphp
        @if ($studentSync)
            <span class="chip">
                <span class="swatch rounded-full" style="background: var({{ $studentSync['status'] === 'success' ? '--good' : ($studentSync['status'] === 'error' ? '--critical' : '--warning') }})"></span>
                HEMIS: {{ $studentSync['finished_at'] ?? 'sinxronlanmoqda…' }}
            </span>
        @endif
    </x-slot:actions>

    {{-- Filtrlar: bitta qatorda, barcha chartlarga ta'sir qiladi --}}
    <form method="GET" action="{{ route('dashboard') }}" id="filters" class="card p-3 mb-6 flex flex-wrap items-center gap-2">
        <select name="department_id" class="field min-w-0 flex-1 sm:flex-none sm:w-80" aria-label="Fakultet">
            <option value="">Barcha fakultetlar</option>
            @foreach ($options['departments'] as $d)
                <option value="{{ $d['id'] }}" @selected(($filters['department_id'] ?? null) == $d['id'])>{{ $d['name'] }} ({{ $n($d['total']) }})</option>
            @endforeach
        </select>
        <select name="education_type" class="field" aria-label="Ta'lim turi">
            <option value="">Barcha ta’lim turlari</option>
            @foreach ($options['education_types'] as $o)
                <option value="{{ $o['code'] }}" @selected(($filters['education_type'] ?? null) === $o['code'])>{{ $o['name'] }}</option>
            @endforeach
        </select>
        <select name="education_form" class="field" aria-label="Ta'lim shakli">
            <option value="">Barcha ta’lim shakllari</option>
            @foreach ($options['education_forms'] as $o)
                <option value="{{ $o['code'] }}" @selected(($filters['education_form'] ?? null) === $o['code'])>{{ $o['name'] }}</option>
            @endforeach
        </select>
        @if (array_filter($filters))
            <a href="{{ route('dashboard') }}" class="btn-ghost">Tozalash ✕</a>
        @endif
        <span class="ml-auto text-xs muted hidden md:inline">Filtrlar barcha ko‘rsatkichlarga qo‘llanadi</span>
    </form>

    {{-- Asosiy ko'rsatkich + KPI --}}
    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,2fr)] gap-4 mb-4">
        <div class="card p-6 flex flex-col justify-between">
            <div>
                <div class="text-xs font-semibold muted uppercase tracking-wide">Reyestrdagi jami shaxslar</div>
                <div class="mt-3 text-[56px] leading-none font-extrabold tracking-tight">{{ $n($k['total']) }}</div>
                <p class="mt-3 text-sm secondary">Universitet tarixidagi barcha talabalar va sobiq talabalar — HEMIS’dagi barcha statuslar bo‘yicha.</p>
            </div>
            @php
                $parts = [
                    ['O‘qimoqda', $k['studying'], '--series-1'],
                    ['Bitirgan', $k['graduated'], '--series-3'],
                    ['Chetlashgan', $k['expelled'], '--series-2'],
                    ['Boshqa', max($k['total'] - $k['studying'] - $k['graduated'] - $k['expelled'], 0), '--series-other'],
                ];
            @endphp
            <div class="mt-6">
                <div class="flex h-3 rounded-full overflow-hidden gap-[2px]" role="img" aria-label="Status bo'yicha taqsimot">
                    @foreach ($parts as [$label, $value, $color])
                        @if ($value > 0)
                            <div style="flex: {{ $value }}; background: var({{ $color }})" title="{{ $label }}: {{ $n($value) }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                    @foreach ($parts as [$label, $value, $color])
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="swatch" style="background: var({{ $color }})"></span>
                            <span class="secondary truncate">{{ $label }}</span>
                            <span class="ml-auto font-bold">{{ $n($value) }}</span>
                            <span class="muted text-xs w-12 text-right">{{ $pct($value, $k['total']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-kpi label="Hozir o‘qiyotganlar" :href="$studentsLink('11')" :value="$n($k['studying'])" :hint="$n($k['active_groups']).' guruh · '.$n($k['active_specialties']).' yo‘nalish'" color="--series-1" />
            <x-kpi label="Bitiruvchilar" :href="$studentsLink('14,18')" :value="$n($k['graduated'])" :hint="$n($k['diplomas']).' ta diplom reyestrda'" color="--series-3" />
            <x-kpi label="Chetlashganlar" :href="$studentsLink('12')" :value="$n($k['expelled'])" :hint="$pct($k['expelled'], $k['total']).' — jami reyestrdan'" color="--series-2" />
            <x-kpi label="Akademik ta’tilda" :href="$studentsLink('13')" :value="$n($k['academic_leave'])" :hint="'Kursdan qolgan: '.$n($k['repeating'])" />
            <x-kpi label="Davlat granti ulushi" :value="$k['grant_share'].'%'" hint="Amaldagi talabalar orasida" />
            <x-kpi label="Xotin-qizlar ulushi" :value="$k['female_share'].'%'" hint="Amaldagi talabalar orasida" />
            <x-kpi label="O‘rtacha GPA" :value="$k['avg_gpa'] ?? '—'" :hint="$k['gpa_year'] ? $k['gpa_year'].'-o‘quv yili' : 'Ma’lumot yo‘q'" />
            <x-kpi label="Imtiyozli diplomlar" :value="$n($k['honors'])" :hint="$pct($k['honors'], $k['diplomas']).' · o‘rt. baho (5 ballik) '.($k['diploma_avg_grade'] ?? '—')" />
        </div>
    </div>

    {{-- Tarixiy dinamika --}}
    <h2 class="text-sm font-bold uppercase tracking-wide muted mt-8 mb-3">Tarixiy dinamika</h2>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <x-chart-card id="cohorts" class="xl:col-span-2" title="Qabul yillari bo‘yicha kogortalar"
                      subtitle="Har bir qabul yilidagi talabalar va ularning hozirgi holati" :height="360" />
        <x-chart-card id="status" title="Status bo‘yicha taqsimot" subtitle="Reyestrdagi barcha shaxslar" :height="360" />
        <x-chart-card id="graduates" class="xl:col-span-2" title="Bitiruvchilar yillar bo‘yicha"
                      subtitle="HEMIS diplom reyestri: jami va imtiyozli diplomlar" :height="320" />
        <x-chart-card id="admission" title="Qabul turi" subtitle="Odatiy qabul, ko‘chirish, xorijiy, mobillik…" :height="320" />
    </div>

    {{-- Amaldagi kontingent --}}
    <h2 class="text-sm font-bold uppercase tracking-wide muted mt-8 mb-3">Amaldagi kontingent</h2>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <x-chart-card id="faculties" class="xl:col-span-2" title="Fakultetlar kesimida" subtitle="Hozir o‘qiyotganlar, jins bo‘yicha" :height="400" />
        <x-chart-card id="levels" title="Kurslar kesimida" subtitle="Ta’lim shakli bo‘yicha" :height="400" />
        <x-chart-card id="gender" title="Jins" :height="260" />
        <x-chart-card id="payment" title="To‘lov shakli" :height="260" />
        <x-chart-card id="edutype" title="Ta’lim turi" :height="260" />
        <x-chart-card id="eduform" title="Ta’lim shakli" subtitle="Hozir o‘qiyotganlar" :height="300" />
        <x-chart-card id="specialties" class="xl:col-span-2" title="Eng ko‘p talabali 10 yo‘nalish" :height="300" />
    </div>

    {{-- Geografiya va ijtimoiy tarkib --}}
    <h2 class="text-sm font-bold uppercase tracking-wide muted mt-8 mb-3">Geografiya va ijtimoiy tarkib</h2>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <x-chart-card id="provinces" class="xl:col-span-2" title="Hududlar bo‘yicha" subtitle="Doimiy yashash viloyati" :height="380" />
        <x-chart-card id="age" title="Yosh taqsimoti" subtitle="Hozir o‘qiyotganlar" :height="380" />
        <x-chart-card id="accommodation" title="Yashash joyi" :height="280" />
        <x-chart-card id="social" title="Ijtimoiy toifa" :height="280" />
        <x-chart-card id="lang" title="Ta’lim tili" :height="280" />
    </div>

    {{-- Akademik ko'rsatkichlar --}}
    <h2 class="text-sm font-bold uppercase tracking-wide muted mt-8 mb-3">Akademik ko‘rsatkichlar</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-chart-card id="gpa" class="xl:col-span-2" title="O‘rtacha GPA" subtitle="O‘quv yillari bo‘yicha" :height="280" />
        <x-chart-card id="debt" class="xl:col-span-2" title="Akademik qarzdorlar ulushi" subtitle="Kamida bitta qarz fanli talabalar, %" :height="280" />
        <x-chart-card id="gpadist" class="xl:col-span-3" title="GPA taqsimoti" :subtitle="'So‘nggi o‘quv yili ('.($k['gpa_year'] ?? '—').')'" :height="280" />
        <x-chart-card id="diplomacat" title="Diplom turi" :height="280" />
    </div>

    {{-- Ma'lumotlar sifati --}}
    <h2 class="text-sm font-bold uppercase tracking-wide muted mt-8 mb-3">Ma’lumotlar sifati</h2>
    <div class="grid grid-cols-1 gap-4">
        <section class="card p-5">
            <div class="flex items-baseline justify-between mb-4">
                <div><h3 class="text-[15px] font-bold">Tekshiruv talab qiladigan holatlar</h3>@if ($canViewPersonalData)<p class="text-xs muted mt-0.5">To‘liq ro‘yxatni ko‘rish uchun bosing</p>@endif</div>
                <span class="text-sm secondary">To‘liqlik: <b class="text-[var(--text-primary)]">{{ $stats['quality']['completeness'] }}%</b></span>
            </div>
            <ul class="divide-y" style="border-color: var(--grid)">
                @foreach ($stats['quality']['items'] as $item)
                    @php
                        $icon = ['ok' => ['✓', '--good', 'Muammo yo‘q'], 'warn' => ['!', '--serious', 'Tekshirish kerak'], 'info' => ['i', '--series-1', 'Ma’lumot']][$item['level']];
                    @endphp
                    <li style="border-color: var(--grid)">
                      <{{ $canViewPersonalData ? 'a' : 'div' }} @if ($canViewPersonalData) href="{{ route('quality.show', ['check' => $item['key']] + array_filter($filters)) }}" @endif
                         class="group flex items-center gap-3 py-3 px-2 -mx-2 rounded-xl hover:bg-[var(--surface-2)] transition-colors">
                        <span class="size-7 rounded-full grid place-items-center text-xs font-extrabold text-white shrink-0" style="background: var({{ $icon[1] }})" aria-label="{{ $icon[2] }}">{{ $icon[0] }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold">{{ $item['label'] }}</div>
                            <div class="text-xs muted">{{ $item['hint'] }}</div>
                        </div>
                        <div class="text-lg font-extrabold">{{ $item['value'] === null ? '—' : $n($item['value']) }}</div>
                        @if ($canViewPersonalData)
                            <span class="muted text-lg group-hover:translate-x-0.5 transition-transform" aria-hidden="true">›</span>
                        @endif
                      </{{ $canViewPersonalData ? 'a' : 'div' }}>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <script type="application/json" id="stats-data">@json($stats)</script>
    @if ($canViewPersonalData)
        {{-- Grafik bosilganda shu manzilga dashboard filtrlari va tanlangan qiymat bilan o'tiladi --}}
        <script type="application/json" id="drill-config">@json(['url' => route('students.index'), 'filters' => array_filter($filters)])</script>
    @endif
</x-layout>
