<?php

namespace App\Services\Stats;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ma'lumotlar sifati tekshiruvlari (PDF 12-bo'lim): soni dashboard uchun,
 * to'liq ro'yxati esa tekshiruv sahifasi va CSV eksport uchun.
 */
class DataQuality
{
    /** Bitiruvchi hisoblanadigan statuslar: Bitirgan, Qarzdor bitiruvchi. */
    public const GRADUATED_STATUSES = ['14', '18'];

    /**
     * @var array<string, array{label: string, hint: string, level: string, subject: string}>
     */
    public const CHECKS = [
        'duplicates' => [
            'label' => 'Ehtimoliy dublikat guruhlar (F.I.Sh. + tug‘ilgan sana)',
            'hint' => 'Bir xil F.I.Sh. va tug‘ilgan sanali yozuvlar — avtomatik birlashtirilmaydi, xodim tekshiradi',
            'level' => 'warn',
            'subject' => 'students',
        ],
        'graduates_without_diploma' => [
            'label' => 'Diplom yozuvi topilmagan bitiruvchilar',
            'hint' => 'Status “Bitirgan”, lekin HEMIS diplom reyestrida yo‘q',
            'level' => 'warn',
            'subject' => 'students',
        ],
        'diplomas_without_student' => [
            'label' => 'Talaba kartasiga bog‘lanmagan diplomlar',
            'hint' => 'Diplom bor, talaba yozuvi sinxronlanmagan',
            'level' => 'warn',
            'subject' => 'diplomas',
        ],
        'missing_birth_date' => [
            'label' => 'Tug‘ilgan sanasi yo‘q',
            'hint' => 'Identifikatsiya uchun muhim fakt',
            'level' => 'info',
            'subject' => 'students',
        ],
        'missing_department' => [
            'label' => 'Fakulteti ko‘rsatilmagan',
            'hint' => 'Akademik biriktirish yetishmaydi',
            'level' => 'info',
            'subject' => 'students',
        ],
        'missing_year' => [
            'label' => 'Qabul yili ko‘rsatilmagan',
            'hint' => 'Tarixiy kontingent hisobiga ta’sir qiladi',
            'level' => 'info',
            'subject' => 'students',
        ],
    ];

    /** Ro'yxatda ko'rsatiladigan talaba ustunlari. */
    public const STUDENT_COLUMNS = [
        'hemis_id', 'student_id_number', 'full_name', 'birth_date', 'gender_name', 'status_name',
        'department_name', 'specialty_name', 'group_name', 'level_name', 'year_of_enter',
        'education_type_name', 'education_form_name', 'payment_form_name', 'province_name',
    ];

    public const DIPLOMA_COLUMNS = [
        'hemis_id', 'student_hemis_id', 'diploma_number', 'register_number', 'register_date',
        'department_name', 'specialty_name', 'group_name', 'education_type_name', 'education_form_name',
        'category_name', 'graduate_year', 'diploma_file',
    ];

    /** @var array{department_id: ?int, education_type: ?string, education_form: ?string} */
    private array $filters = ['department_id' => null, 'education_type' => null, 'education_form' => null];

    /**
     * @param  array{department_id?: ?int, education_type?: ?string, education_form?: ?string}  $filters
     */
    public function withFilters(array $filters): static
    {
        $clone = clone $this;
        $clone->filters = array_merge($this->filters, array_filter(
            array_intersect_key($filters, $this->filters),
            fn ($value) => $value !== null && $value !== ''
        ));

        return $clone;
    }

    public function hasFilters(): bool
    {
        return (bool) array_filter($this->filters);
    }

    /** Filtrlangan talabalar (barcha statuslar). */
    public function students(): Builder
    {
        return DB::table('students')
            ->when($this->filters['department_id'], fn (Builder $q, $v) => $q->where('students.department_id', $v))
            ->when($this->filters['education_type'], fn (Builder $q, $v) => $q->where('students.education_type_code', $v))
            ->when($this->filters['education_form'], fn (Builder $q, $v) => $q->where('students.education_form_code', $v));
    }

    /**
     * Dashboard uchun barcha tekshiruvlar soni.
     *
     * @return array{completeness: float, items: array<int, array{key: string, label: string, hint: string, value: ?int, level: string}>}
     */
    public function summary(): array
    {
        $total = max($this->students()->count(), 1);
        $counts = [];
        foreach (array_keys(self::CHECKS) as $key) {
            $counts[$key] = $this->count($key);
        }

        $missing = $counts['missing_birth_date'] + $counts['missing_department'] + $counts['missing_year'];
        $duplicateRecords = $this->duplicateGroups()->sum('c');

        return [
            'completeness' => round(100 - ($missing / (3 * $total) * 100), 1),
            'items' => collect(self::CHECKS)->map(fn (array $check, string $key) => [
                'key' => $key,
                'label' => $check['label'],
                'hint' => $key === 'duplicates' && $counts[$key] > 0
                    ? number_format($duplicateRecords, 0, ',', ' ').' ta yozuv xodim tekshiruvini talab qiladi'
                    : $check['hint'],
                'value' => $counts[$key],
                'level' => $counts[$key] ? $check['level'] : 'ok',
            ])->values()->all(),
        ];
    }

    /** Tekshiruv bo'yicha yozuvlar soni (dublikatlarda — guruhlar soni). */
    public function count(string $key): ?int
    {
        if ($key === 'diplomas_without_student' && $this->hasFilters()) {
            return null; // bog'lanmagan diplomni fakultet filtri bilan aniqlab bo'lmaydi
        }

        return $key === 'duplicates'
            ? DB::query()->fromSub($this->duplicateGroups(), 'd')->count()
            : $this->query($key)->count();
    }

    /**
     * Tekshiruv bo'yicha yozuvlar so'rovi (dublikatlardan tashqari).
     */
    public function query(string $key, ?string $search = null): Builder
    {
        $query = match ($key) {
            'graduates_without_diploma' => $this->students()
                ->whereIn('status_code', self::GRADUATED_STATUSES)
                ->whereNotExists(fn (Builder $q) => $q->from('diplomas')->whereColumn('diplomas.student_hemis_id', 'students.hemis_id')),
            'diplomas_without_student' => DB::table('diplomas')
                ->whereNotExists(fn (Builder $q) => $q->from('students')->whereColumn('students.hemis_id', 'diplomas.student_hemis_id')),
            'missing_birth_date' => $this->students()->whereNull('birth_date'),
            'missing_department' => $this->students()->whereNull('department_id'),
            'missing_year' => $this->students()->whereNull('year_of_enter'),
            'duplicates' => $this->duplicateStudents(),
        };

        $isDiploma = self::CHECKS[$key]['subject'] === 'diplomas';

        return $query
            ->when($search, fn (Builder $q, string $s) => $this->applySearch($q, $s, $isDiploma))
            ->select($isDiploma ? self::DIPLOMA_COLUMNS : array_map(fn ($c) => 'students.'.$c, self::STUDENT_COLUMNS))
            ->when(! $isDiploma && $key !== 'duplicates', fn (Builder $q) => $q->orderBy('students.full_name'))
            ->when($isDiploma, fn (Builder $q) => $q->orderByDesc('graduate_year')->orderBy('diploma_number'));
    }

    /** Dublikat kalitlari: upper(F.I.Sh.) + tug'ilgan sana, 2 va undan ortiq yozuv. */
    public function duplicateGroups(?string $search = null): Builder
    {
        return $this->students()->whereNotNull('birth_date')
            ->when($search, fn (Builder $q, string $s) => $this->applySearch($q, $s, false))
            ->selectRaw('upper(full_name) as name_key, birth_date, count(*) as c')
            ->groupByRaw('upper(full_name), birth_date')
            ->havingRaw('count(*) > 1');
    }

    /** Dublikat guruhlariga kiruvchi barcha talabalar (guruh bo'yicha tartiblangan). */
    private function duplicateStudents(): Builder
    {
        return $this->students()
            ->joinSub($this->duplicateGroups(), 'dup', function ($join) {
                $join->on(DB::raw('upper(students.full_name)'), '=', 'dup.name_key')
                    ->on('students.birth_date', '=', 'dup.birth_date');
            })
            ->orderBy('dup.name_key')->orderBy('dup.birth_date')->orderBy('students.year_of_enter');
    }

    /**
     * Dublikatlar sahifasi: guruhlar bo'yicha sahifalanadi (guruh ikki sahifaga bo'linmaydi).
     *
     * @return array{groups: LengthAwarePaginator, students: Collection<string, Collection<int, object>>}
     */
    public function duplicatePage(?string $search, int $perPage = 40): array
    {
        $groups = DB::query()->fromSub($this->duplicateGroups($search), 'g')
            ->orderBy('name_key')->orderBy('birth_date')
            ->paginate($perPage)->withQueryString();

        $students = collect();
        if ($groups->isNotEmpty()) {
            $students = $this->students()
                ->where(function (Builder $q) use ($groups) {
                    foreach ($groups->items() as $group) {
                        $q->orWhere(fn (Builder $w) => $w->whereRaw('upper(full_name) = ?', [$group->name_key])
                            ->where('birth_date', $group->birth_date));
                    }
                })
                ->select(array_map(fn ($c) => 'students.'.$c, self::STUDENT_COLUMNS))
                ->selectRaw('upper(students.full_name) as name_key')
                ->orderBy('year_of_enter')->orderBy('hemis_id')
                ->get()
                ->groupBy(fn ($s) => $s->name_key.'|'.$s->birth_date);
        }

        return ['groups' => $groups, 'students' => $students];
    }

    private function applySearch(Builder $query, string $search, bool $isDiploma): Builder
    {
        $term = '%'.mb_strtoupper(trim($search)).'%';

        return $query->where(function (Builder $q) use ($term, $isDiploma, $search) {
            if ($isDiploma) {
                $q->whereRaw('upper(diploma_number) like ?', [$term])
                    ->orWhereRaw('upper(specialty_name) like ?', [$term])
                    ->orWhereRaw('upper(group_name) like ?', [$term]);
            } else {
                $q->whereRaw('upper(students.full_name) like ?', [$term])
                    ->orWhere('students.student_id_number', 'like', '%'.trim($search).'%')
                    ->orWhereRaw('upper(students.group_name) like ?', [$term]);
            }
        });
    }
}
