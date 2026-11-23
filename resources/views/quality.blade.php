@php
    $n = fn ($v) => $v === null ? '—' : number_format($v, 0, ',', ' ');
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : '—';
    $paginator = $check === 'duplicates' ? $groups : $rows;
    $current = collect($checks)->firstWhere('key', $check);

    /** Talaba jadvali ustunlari: [maydon, sarlavha, formatlash]. */
    $studentColumns = [
        ['student_id_number', 'Talaba ID', null],
        ['full_name', 'F.I.Sh.', 'student'],
        ['birth_date', 'Tug‘ilgan sana', 'date'],
        ['status_name', 'Status', 'status'],
        ['department_name', 'Fakultet', null],
        ['specialty_name', 'Yo‘nalish', null],
        ['group_name', 'Guruh', null],
        ['year_of_enter', 'Qabul', null],
        ['education_type_name', 'Ta’lim turi', null],
        ['education_form_name', 'Shakl', null],
        ['hemis_id', 'HEMIS ID', null],
    ];
    $diplomaColumns = [
        ['diploma_number', 'Diplom raqami', null],
        ['register_number', 'Qayd raqami', null],
        ['register_date', 'Qayd sanasi', 'date'],
        ['specialty_name', 'Yo‘nalish', null],
        ['group_name', 'Guruh', null],
        ['education_form_name', 'Shakl', null],
        ['category_name', 'Turi', null],
        ['graduate_year', 'Bitirgan', null],
        ['student_hemis_id', 'Talaba HEMIS ID', null],
        ['diploma_file', 'Fayl', 'link'],
    ];
    $columns = $isDiploma ? $diplomaColumns : $studentColumns;

    $statusColor = fn (?string $status) => match (true) {
        $status === null => '--series-other',
        str_contains($status, 'O‘qimoqda') => '--series-1',
        str_contains($status, 'Bitirgan') || str_contains($status, 'bitiruvchi') => '--series-3',
        str_contains($status, 'Chetlash') => '--series-2',
        default => '--series-other',
    };
    $cell = function ($row, array $column) use ($date, $statusColor) {
        [$field, , $format] = $column;
        $value = $row->{$field} ?? null;

        return match ($format) {
            'date' => e($date($value)),
            'status' => '<span class="inline-flex items-center gap-1.5 whitespace-nowrap"><span class="swatch rounded-full" style="background: var('.$statusColor($value).')"></span>'.e($value ?? '—').'</span>',
            'link' => $value ? '<a href="'.e($value).'" target="_blank" rel="noopener" class="font-semibold" style="color: var(--accent)">PDF ↗</a>' : '—',
            'student' => isset($row->hemis_id) ? '<a href="'.e(route('students.show', $row->hemis_id)).'" class="hover:underline">'.e($value).'</a>' : e($value),
            default => $value === null || $value === '' ? '<span class="muted">—</span>' : e($value),
        };
    };
@endphp

<x-layout :title="$definition['label']">
    <x-slot:actions>
        <a href="{{ route('dashboard', $filters) }}" class="nav-btn">← Dashboard</a>
    </x-slot:actions>

    <div class="grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-4 items-start">

        {{-- Tekshiruvlar ro'yxati --}}
        <nav class="card p-2 lg:sticky lg:top-4 flex lg:flex-col gap-1 overflow-x-auto" aria-label="Tekshiruvlar">
            <div class="hidden lg:block px-3 pt-2 pb-1 text-xs font-bold uppercase tracking-wide muted">Ma’lumotlar sifati</div>
            @foreach ($checks as $item)
                @php $active = $item['key'] === $check; @endphp
                <a href="{{ route('quality.show', ['check' => $item['key']] + $filters) }}"
                   @if ($active) aria-current="page" @endif
                   class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm shrink-0 lg:shrink transition-colors {{ $active ? 'font-bold' : 'secondary hover:bg-[var(--surface-2)]' }}"
                   style="{{ $active ? 'background: var(--accent-soft); color: var(--text-primary)' : '' }}">
                    <span class="swatch rounded-full" style="background: var({{ ['ok' => '--good', 'warn' => '--serious', 'info' => '--series-1'][$item['level']] }})"></span>
                    <span class="min-w-0 lg:flex-1 whitespace-nowrap lg:whitespace-normal leading-snug">{{ $item['label'] }}</span>
                    <span class="font-bold tabular-nums">{{ $n($item['value']) }}</span>
                </a>
            @endforeach
        </nav>

        <main class="min-w-0 flex flex-col gap-4">
            {{-- Sarlavha va asboblar --}}
            <section class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h1 class="text-xl font-extrabold tracking-tight">{{ $definition['label'] }}</h1>
                        <p class="text-sm secondary mt-1">{{ $current['hint'] ?? $definition['hint'] }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-extrabold leading-none">{{ $n($paginator?->total()) }}</div>
                        <div class="text-xs muted mt-1">{{ $check === 'duplicates' ? 'guruh' : 'yozuv' }}{{ $search ? ' (qidiruv bo‘yicha)' : '' }}</div>
                    </div>
                </div>

                <form method="GET" action="{{ route('quality.show', $check) }}" id="filters" class="mt-4 flex flex-wrap items-center gap-2">
                    <input type="search" name="q" value="{{ $search }}" placeholder="F.I.Sh., talaba ID yoki guruh…"
                           class="field !bg-none !pr-3 flex-1 min-w-[220px]" aria-label="Qidiruv">
                    <select name="department_id" class="field min-w-0 sm:w-64" aria-label="Fakultet">
                        <option value="">Barcha fakultetlar</option>
                        @foreach ($options['departments'] as $d)
                            <option value="{{ $d['id'] }}" @selected(($filters['department_id'] ?? null) == $d['id'])>{{ $d['name'] }}</option>
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
                    <button type="submit" class="field !bg-none !pr-3 font-semibold">Qidirish</button>
                    @if ($search || $filters)
                        <a href="{{ route('quality.show', $check) }}" class="btn-ghost">Tozalash ✕</a>
                    @endif
                    @can('export-data')
                    <a href="{{ route('quality.export', ['check' => $check] + $filters + ($search ? ['q' => $search] : [])) }}"
                       class="ml-auto inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white" style="background: var(--accent-strong, #1c5cab)">
                        ⬇ CSV (Excel)
                    </a>
                    @endcan
                </form>
            </section>

            @if ($unavailable)
                <section class="card p-8 text-center secondary">Bu tekshiruv fakultet yoki ta’lim filtri bilan hisoblanmaydi — bog‘lanmagan diplomning talabasi noma’lum. Filtrlarni tozalang.</section>
            @elseif ($paginator->isEmpty())
                <section class="card p-10 text-center">
                    <div class="size-12 mx-auto rounded-full grid place-items-center text-white font-extrabold" style="background: var(--good)">✓</div>
                    <p class="mt-3 font-semibold">Hech narsa topilmadi</p>
                    <p class="text-sm muted">{{ $search ? 'Qidiruv so‘rovini o‘zgartirib ko‘ring.' : 'Bu tekshiruv bo‘yicha muammo yo‘q.' }}</p>
                </section>
            @elseif ($check === 'duplicates')
                {{-- Dublikat guruhlari: har bir guruh alohida karta, farq qiladigan maydonlar ajratiladi --}}
                <p class="text-xs muted px-1">
                    <span class="inline-block px-1.5 rounded" style="background: color-mix(in srgb, var(--warning) 28%, transparent)">Ajratilgan</span>
                    katakchalar — guruh ichida farq qiladigan qiymatlar. Ular bir shaxsning turli o‘qish davrlari yoki haqiqiy dublikat ekanini aniqlashga yordam beradi.
                </p>
                @foreach ($groups->items() as $index => $group)
                    @php
                        $members = $students->get($group->name_key.'|'.$group->birth_date, collect());
                        $differs = collect($columns)->mapWithKeys(fn ($c) => [$c[0] => $members->pluck($c[0])->unique()->count() > 1]);
                        $number = $groups->firstItem() + $index;
                    @endphp
                    <section class="card overflow-hidden">
                        <header class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3" style="background: var(--surface-2)">
                            <span class="text-xs font-bold muted tabular-nums">#{{ $number }}</span>
                            <h2 class="font-bold">{{ $members->first()->full_name ?? $group->name_key }}</h2>
                            <span class="text-sm secondary">{{ $date($group->birth_date) }}</span>
                            <span class="chip ml-auto">{{ $group->c }} ta yozuv</span>
                        </header>
                        <div class="overflow-x-auto">
                            <table class="data-table min-w-[900px]">
                                <thead><tr>
                                    @foreach ($columns as $column)
                                        @continue(in_array($column[0], ['full_name', 'birth_date']))
                                        <th class="whitespace-nowrap">{{ $column[1] }}</th>
                                    @endforeach
                                </tr></thead>
                                <tbody>
                                @foreach ($members as $student)
                                    <tr>
                                        @foreach ($columns as $column)
                                            @continue(in_array($column[0], ['full_name', 'birth_date']))
                                            <td @if ($differs[$column[0]]) style="background: color-mix(in srgb, var(--warning) 16%, transparent)" @endif>{!! $cell($student, $column) !!}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            @else
                <section class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="data-table min-w-[1280px]">
                            <thead><tr>
                                <th class="num w-12">№</th>
                                @foreach ($columns as $column)
                                    <th class="whitespace-nowrap">{{ $column[1] }}</th>
                                @endforeach
                            </tr></thead>
                            <tbody>
                            @foreach ($rows->items() as $index => $row)
                                <tr class="hover:bg-[var(--surface-2)]">
                                    <td class="num muted">{{ $rows->firstItem() + $index }}</td>
                                    @foreach ($columns as $column)
                                        <td class="{{ $column[0] === 'full_name' ? 'font-semibold !text-[var(--text-primary)]' : '' }} {{ in_array($column[0], ['full_name', 'group_name', 'student_id_number', 'diploma_number', 'register_number']) ? 'whitespace-nowrap' : '' }}">{!! $cell($row, $column) !!}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if (! $unavailable && $paginator->hasPages())
                <x-pagination :paginator="$paginator" />
            @endif
        </main>
    </div>
</x-layout>
