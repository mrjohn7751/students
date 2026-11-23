@php
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : null;
    $name = fn (?array $classifier) => $classifier['name'] ?? null;

    /** Karta bloklari: [sarlavha, qiymat] — bo'sh qiymatlar "—" bilan ko'rsatiladi. */
    $personal = [
        ['Tug‘ilgan sana', $date($student->birth_date)],
        ['Jinsi', $student->gender_name],
        ['Fuqaroligi', $student->citizenship_name],
        ['Davlat', $student->country_name],
        ['Viloyat', $student->province_name],
        ['Tuman', $student->district_name],
        ['Hozirgi yashash joyi', collect([$name($raw['currentProvince'] ?? null), $name($raw['currentDistrict'] ?? null)])->filter()->join(', ') ?: null],
        ['Ijtimoiy toifa', $student->social_category_name],
        ['Turar joy', $student->accommodation_name],
        ['Oldingi ta’lim', $raw['other'] ?? null],
    ];
    $education = [
        ['Fakultet', $student->department_name],
        ['Yo‘nalish', trim(($student->specialty_code ? $student->specialty_code.' — ' : '').$student->specialty_name) ?: null],
        ['Guruh', $student->group_name],
        ['Kurs / semestr', collect([$student->level_name, $name($raw['semester'] ?? null)])->filter()->join(' · ') ?: null],
        ['O‘quv yili', $student->education_year_name],
        ['Ta’lim turi', $student->education_type_name],
        ['Ta’lim shakli', $student->education_form_name],
        ['Ta’lim tili', $student->education_lang_name],
        ['To‘lov shakli', $student->payment_form_name],
        ['Qabul yili', $student->year_of_enter],
        ['Qabul turi', $student->admission_type_name],
        ['O‘qishni ko‘chirish', collect([$student->transfer_type_name, $student->transfer_university])->filter()->join(' · ') ?: null],
        ['Akademik mobillik', $student->academic_mobile_type_name],
    ];
@endphp

<x-layout :title="$student->full_name">
    <x-slot:actions>
        <a href="{{ url()->previous() !== url()->current() && str_contains(url()->previous(), '/talabalar') ? url()->previous() : route('students.index') }}" class="nav-btn">← Talabalar</a>
    </x-slot:actions>

    {{-- Sarlavha --}}
    <section class="card p-5 mb-4 flex flex-wrap items-center gap-5">
        @if ($student->image)
            <img src="{{ $student->image }}" alt="" class="size-24 rounded-2xl object-cover" style="background: var(--surface-2)" loading="lazy" referrerpolicy="no-referrer">
        @else
            <div class="size-24 rounded-2xl grid place-items-center text-2xl font-extrabold muted" style="background: var(--surface-2)">
                {{ mb_substr($student->second_name ?? $student->full_name, 0, 1) }}{{ mb_substr($student->first_name ?? '', 0, 1) }}
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-extrabold tracking-tight">{{ $student->full_name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge :status="$student->status_name" class="chip" />
                @if ($student->level_name)<span class="chip">{{ $student->level_name }}</span>@endif
                @if ($student->education_type_name)<span class="chip">{{ $student->education_type_name }}</span>@endif
                @if ($student->group_name)<span class="chip">{{ $student->group_name }}</span>@endif
            </div>
        </div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
            <dt class="muted">Talaba ID</dt><dd class="font-semibold tabular-nums">{{ $student->student_id_number ?? '—' }}</dd>
            <dt class="muted">HEMIS ID</dt><dd class="font-semibold tabular-nums">{{ $student->hemis_id }}</dd>
            <dt class="muted">HEMIS’da yangilangan</dt><dd class="tabular-nums">{{ $student->hemis_updated_at?->format('d.m.Y H:i') ?? '—' }}</dd>
            <dt class="muted">Reyestrga olingan</dt><dd class="tabular-nums">{{ $student->updated_at?->format('d.m.Y H:i') ?? '—' }}</dd>
        </dl>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-4 items-start">
        <div class="flex flex-col gap-4">
            @foreach (['Shaxsiy ma’lumotlar' => $personal, 'O‘qish ma’lumotlari' => $education] as $heading => $rows)
                <section class="card p-5">
                    <h2 class="font-bold mb-3">{{ $heading }}</h2>
                    <dl class="grid grid-cols-[minmax(0,11rem)_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                        @foreach ($rows as [$label, $value])
                            <dt class="muted">{{ $label }}</dt>
                            <dd class="{{ $value === null || $value === '' ? 'muted' : '' }}">{{ $value === null || $value === '' ? '—' : $value }}</dd>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>

        <div class="flex flex-col gap-4">
            {{-- Buyruqlar: manba hali ulanmagan --}}
            <section class="card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-bold">Buyruqlar</h2>
                    <span class="chip">Manba ulanmagan</span>
                </div>
                <p class="text-sm secondary mt-2">
                    Qabul, kursdan-kursga o‘tkazish, akademik ta’til, chetlashtirish, tiklash va bitirish buyruqlari shu yerda xronologik tartibda ko‘rsatiladi.
                    HEMIS REST API’da buyruqlar ro‘yxati hozircha mavjud emas — manba (HEMIS endpoint yoki buyruqlar jurnali) ulangach to‘ldiriladi.
                </p>
            </section>

            {{-- O'qish tarixi: yillik GPA yozuvlari bo'yicha --}}
            <section class="card overflow-hidden">
                <header class="px-5 pt-5 pb-3 flex items-center justify-between gap-3">
                    <h2 class="font-bold">O‘qish tarixi (o‘quv yillari bo‘yicha)</h2>
                    <span class="text-xs muted">HEMIS · GPA</span>
                </header>
                @if ($gpas->isEmpty())
                    <p class="px-5 pb-5 text-sm muted">HEMIS’da o‘quv yillari bo‘yicha yozuv yo‘q.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr>
                                <th>O‘quv yili</th>
                                <th>Kurs</th>
                                <th class="num">GPA</th>
                                <th class="num">Kredit</th>
                                <th class="num">Fanlar</th>
                                <th class="num">Qarzdorlik</th>
                                <th>Keyingi kursga</th>
                            </tr></thead>
                            <tbody>
                            @foreach ($gpas as $gpa)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $gpa->education_year_name ?? '—' }}</td>
                                    <td class="whitespace-nowrap">{{ $gpa->level_name ?? '—' }}</td>
                                    <td class="num font-semibold">{{ $gpa->gpa ?? '—' }}</td>
                                    <td class="num">{{ $gpa->credit_sum ?? '—' }}</td>
                                    <td class="num">{{ $gpa->subjects ?? '—' }}</td>
                                    <td class="num {{ $gpa->debt_subjects ? 'font-bold' : '' }}" @if ($gpa->debt_subjects) style="color: var(--serious)" @endif>{{ $gpa->debt_subjects ?? '—' }}</td>
                                    <td>{{ $gpa->can_transfer === null ? '—' : ($gpa->can_transfer ? 'Ha' : 'Yo‘q') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Diplom --}}
            <section class="card p-5">
                <h2 class="font-bold mb-3">Bitirish va diplom</h2>
                @forelse ($diplomas as $diploma)
                    <dl class="grid grid-cols-[minmax(0,11rem)_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm {{ $loop->first ? '' : 'mt-4 pt-4 border-t border-[var(--border)]' }}">
                        <dt class="muted">Diplom raqami</dt><dd class="font-semibold">{{ $diploma->diploma_number ?? '—' }}</dd>
                        <dt class="muted">Turi</dt><dd>{{ $diploma->category_name ?? '—' }}</dd>
                        <dt class="muted">Qayd raqami / sanasi</dt><dd>{{ $diploma->register_number ?? '—' }} · {{ $date($diploma->register_date) ?? '—' }}</dd>
                        <dt class="muted">O‘qish davri</dt><dd>{{ $date($diploma->enroll_date) ?? '—' }} — {{ $date($diploma->graduate_date) ?? '—' }}</dd>
                        <dt class="muted">Yo‘nalish</dt><dd>{{ trim(($diploma->specialty_code ? $diploma->specialty_code.' — ' : '').$diploma->specialty_name) ?: '—' }}</dd>
                        <dt class="muted">O‘rtacha baho</dt><dd>{{ $diploma->avg_grade ?? '—' }}</dd>
                        @if ($diploma->diploma_file)
                            <dt class="muted">Fayl</dt><dd><a href="{{ $diploma->diploma_file }}" target="_blank" rel="noopener" class="font-semibold" style="color: var(--accent)">Diplom PDF ↗</a></dd>
                        @endif
                    </dl>
                @empty
                    <p class="text-sm muted">HEMIS’da bu yozuvga bog‘langan diplom yo‘q.</p>
                @endforelse
            </section>

            {{-- Shu shaxsning boshqa yozuvlari --}}
            @if ($relatedRecords->isNotEmpty())
                <section class="card p-5">
                    <h2 class="font-bold">Shu shaxsning boshqa yozuvlari</h2>
                    <p class="text-xs muted mt-1 mb-3">F.I.Sh. va tug‘ilgan sana bo‘yicha mos kelgan HEMIS yozuvlari (masalan, bakalavr va magistratura). Avtomatik birlashtirilmaydi.</p>
                    <ul class="flex flex-col gap-2 text-sm">
                        @foreach ($relatedRecords as $record)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <a href="{{ route('students.show', $record->hemis_id) }}" class="font-semibold hover:underline" style="color: var(--accent)">HEMIS {{ $record->hemis_id }}</a>
                                <x-status-badge :status="$record->status_name" />
                                <span class="secondary">{{ collect([$record->education_type_name, $record->specialty_name, $record->group_name, $record->year_of_enter])->filter()->join(' · ') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-layout>
