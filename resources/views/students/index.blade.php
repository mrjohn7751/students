@php
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : '—';
    $dash = fn ($v) => $v === null || $v === '' ? '—' : $v;
    /** Select'larda ko'rinmaydigan (grafikdan kelgan) filtrlar — qidiruvda yo'qolmasligi uchun yashirin maydonlar. */
    $selectKeys = ['q', 'department_id', 'status', 'education_type', 'education_form', 'year_of_enter', 'page'];
    $hiddenInputs = \Illuminate\Support\Arr::dot(\Illuminate\Support\Arr::except(request()->query(), $selectKeys));
    $removeUrl = fn (array $keys) => route('students.index', \Illuminate\Support\Arr::except(request()->query(), [...$keys, 'page']));
@endphp

<x-layout title="Talabalar">
    <x-slot:actions>
        <a href="{{ route('dashboard') }}" class="chip hover:opacity-80">← Dashboard</a>
    </x-slot:actions>

    <section class="card p-5 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold tracking-tight">Talabalar reyestri</h1>
                <p class="text-sm secondary mt-1">Barcha statusdagi talabalar (o‘qiyotgan, bitirgan, chetlashtirilgan). Kartani ochish uchun talabani tanlang.</p>
            </div>
            <div class="text-right">
                <div class="text-3xl font-extrabold leading-none">{{ number_format($students->total(), 0, ',', ' ') }}</div>
                <div class="text-xs muted mt-1">yozuv{{ $search !== '' || $filters ? ' (filtr bo‘yicha)' : '' }}</div>
            </div>
        </div>

        <form method="GET" action="{{ route('students.index') }}" class="mt-4 flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="F.I.Sh., talaba ID, HEMIS ID yoki guruh…" autofocus
                   class="field !bg-none !pr-3 flex-1 min-w-[240px]" aria-label="Qidiruv">
            <select name="department_id" class="field min-w-0 sm:w-64" aria-label="Fakultet">
                <option value="">Barcha fakultetlar</option>
                @foreach ($options['departments'] as $d)
                    <option value="{{ $d['id'] }}" @selected(($filters['department_id'] ?? null) == $d['id'])>{{ $d['name'] }}</option>
                @endforeach
            </select>
            <select name="status" class="field" aria-label="Status">
                <option value="">Barcha statuslar</option>
                @if (($filters['status'] ?? null) === 'other')
                    <option value="other" selected>Boshqa statuslar</option>
                @endif
                @if (str_contains($filters['status'] ?? '', ','))
                    <option value="{{ $filters['status'] }}" selected>
                        {{ collect($options['statuses'])->whereIn('code', explode(',', $filters['status']))->pluck('name')->join(' + ') }}
                    </option>
                @endif
                @foreach ($options['statuses'] as $o)
                    <option value="{{ $o['code'] }}" @selected(($filters['status'] ?? null) === $o['code'])>{{ $o['name'] }}</option>
                @endforeach
            </select>
            <select name="education_type" class="field" aria-label="Ta'lim turi">
                <option value="">Barcha ta’lim turlari</option>
                @foreach ($options['education_types'] as $o)
                    <option value="{{ $o['code'] }}" @selected(($filters['education_type'] ?? null) === $o['code'])>{{ $o['name'] }}</option>
                @endforeach
            </select>
            <select name="education_form" class="field" aria-label="Ta'lim shakli">
                <option value="">Barcha shakllar</option>
                @foreach ($options['education_forms'] as $o)
                    <option value="{{ $o['code'] }}" @selected(($filters['education_form'] ?? null) === $o['code'])>{{ $o['name'] }}</option>
                @endforeach
            </select>
            <select name="year_of_enter" class="field" aria-label="Qabul yili">
                <option value="">Qabul yili</option>
                @foreach ($options['years'] as $year)
                    <option value="{{ $year }}" @selected(($filters['year_of_enter'] ?? null) == $year)>{{ $year }}</option>
                @endforeach
            </select>
            @foreach ($hiddenInputs as $key => $value)
                @php $name = collect(explode('.', $key))->map(fn ($part, $i) => $i ? "[{$part}]" : $part)->join(''); @endphp
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <button type="submit" class="field !bg-none !pr-3 font-semibold">Qidirish</button>
            @if ($search !== '' || $filters)
                <a href="{{ route('students.index') }}" class="btn-ghost">Tozalash ✕</a>
            @endif
        </form>

        @if ($chips)
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="text-xs muted">Grafikdan tanlangan:</span>
                @foreach ($chips as $chip)
                    <a href="{{ $removeUrl($chip['remove']) }}" class="chip hover:opacity-80" title="Filtrni olib tashlash"
                       style="background: var(--accent-soft); color: var(--text-primary)">
                        <span class="muted">{{ $chip['label'] }}:</span> {{ $chip['value'] }} <span aria-hidden="true">✕</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if ($students->isEmpty())
        <section class="card p-10 text-center">
            <p class="font-semibold">Hech narsa topilmadi</p>
            <p class="text-sm muted">Qidiruv so‘rovi yoki filtrlarni o‘zgartirib ko‘ring.</p>
        </section>
    @else
        <section class="card overflow-hidden mb-4">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[1200px]">
                    <thead><tr>
                        <th class="num w-12">№</th>
                        <th>F.I.Sh.</th>
                        <th class="whitespace-nowrap">Tug‘ilgan sana</th>
                        <th>Status</th>
                        <th>Fakultet</th>
                        <th>Yo‘nalish</th>
                        <th>Guruh</th>
                        <th>Kurs</th>
                        <th>Qabul</th>
                        <th class="whitespace-nowrap">Talaba ID</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($students->items() as $index => $student)
                        <tr class="hover:bg-[var(--surface-2)]">
                            <td class="num muted">{{ $students->firstItem() + $index }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('students.show', $student->hemis_id) }}" class="font-semibold hover:underline" style="color: var(--accent)">{{ $student->full_name }}</a>
                            </td>
                            <td>{{ $date($student->birth_date) }}</td>
                            <td><x-status-badge :status="$student->status_name" /></td>
                            <td>{{ $dash($student->department_name) }}</td>
                            <td>{{ $dash($student->specialty_name) }}</td>
                            <td class="whitespace-nowrap">{{ $dash($student->group_name) }}</td>
                            <td class="whitespace-nowrap">{{ $dash($student->level_name) }}</td>
                            <td>{{ $dash($student->year_of_enter) }}</td>
                            <td class="whitespace-nowrap tabular-nums">{{ $dash($student->student_id_number) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($students->hasPages())
            <x-pagination :paginator="$students" />
        @endif
    @endif
</x-layout>
