<?php

namespace App\Services\Stats;

use App\Models\Student;
use App\Models\SyncLog;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard uchun umumiy statistikani hisoblaydi.
 * Natijalar filtr kombinatsiyasi bo'yicha keshlanadi; sinxronizatsiyadan so'ng kesh versiyasi yangilanadi.
 */
class DashboardStats
{
    private const CACHE_TTL = 600;

    /** @var array{department_id: ?int, education_type: ?string, education_form: ?string} */
    private array $filters = ['department_id' => null, 'education_type' => null, 'education_form' => null];

    /**
     * @param  array{department_id?: ?int, education_type?: ?string, education_form?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $this->filters = array_merge($this->filters, array_filter($filters, fn ($v) => $v !== null && $v !== ''));
        $version = Cache::get('stats.version', 1);
        $key = 'stats.'.$version.'.'.md5(json_encode($this->filters));

        return Cache::remember($key, self::CACHE_TTL, fn () => $this->compute());
    }

    public function flush(): void
    {
        Cache::forever('stats.version', Cache::get('stats.version', 1) + 1);
    }

    /**
     * Filtr ro'yxatlari uchun variantlar (keshsiz emas — kam o'zgaradi).
     *
     * @return array{departments: array, education_types: array, education_forms: array}
     */
    public function filterOptions(): array
    {
        return Cache::remember('stats.'.Cache::get('stats.version', 1).'.options', self::CACHE_TTL, fn () => [
            'departments' => DB::table('students')->whereNotNull('department_id')
                ->select('department_id as id', 'department_name as name', DB::raw('count(*) as total'))
                ->groupBy('department_id', 'department_name')->orderBy('department_name')->get()
                ->map(fn ($row) => (array) $row)->all(),
            'education_types' => $this->distinct('education_type'),
            'education_forms' => $this->distinct('education_form'),
        ]);
    }

    /** @return array<string, mixed> */
    private function compute(): array
    {
        return [
            'kpi' => $this->kpi(),
            'status' => $this->breakdown($this->students(), 'status_name'),
            'cohorts' => $this->cohorts(),
            'graduates' => $this->graduatesByYear(),
            'faculties' => $this->faculties(),
            'levels' => $this->levels(),
            'education_form' => $this->breakdown($this->studying(), 'education_form_name'),
            'education_type' => $this->breakdown($this->studying(), 'education_type_name'),
            'payment_form' => $this->breakdown($this->studying(), 'payment_form_name'),
            'gender' => $this->breakdown($this->studying(), 'gender_name'),
            'provinces' => $this->breakdown($this->studying(), 'province_name', 14),
            'specialties' => $this->breakdown($this->studying(), 'specialty_name', 10),
            'accommodation' => $this->breakdown($this->studying(), 'accommodation_name'),
            'social_category' => $this->breakdown($this->studying(), 'social_category_name'),
            'education_lang' => $this->breakdown($this->studying(), 'education_lang_name'),
            'admission_type' => $this->breakdown($this->students(), 'admission_type_name'),
            'citizenship' => $this->breakdown($this->studying(), 'citizenship_name'),
            'age' => $this->ages(),
            'gpa_by_year' => $this->gpaByYear(),
            'gpa_distribution' => $this->gpaDistribution(),
            'diploma_category' => $this->breakdown($this->diplomas(), 'category_name'),
            'quality' => $this->quality(),
            'sync' => $this->lastSync(),
        ];
    }

    /** Filtrlangan talabalar so'rovi (barcha statuslar). */
    private function students(): Builder
    {
        return DB::table('students')
            ->when($this->filters['department_id'], fn (Builder $q, $v) => $q->where('students.department_id', $v))
            ->when($this->filters['education_type'], fn (Builder $q, $v) => $q->where('students.education_type_code', $v))
            ->when($this->filters['education_form'], fn (Builder $q, $v) => $q->where('students.education_form_code', $v));
    }

    /** Hozirda o'qiyotganlar (amaldagi kontingent). */
    private function studying(): Builder
    {
        return $this->students()->where('students.status_code', Student::STATUS_STUDYING);
    }

    /** Diplomlar — talaba filtri bilan bog'langan holda. */
    private function diplomas(): Builder
    {
        $query = DB::table('diplomas');
        if ($this->hasFilters()) {
            $query->whereIn('diplomas.student_hemis_id', $this->students()->select('hemis_id'));
        }

        return $query;
    }

    private function hasFilters(): bool
    {
        return (bool) array_filter($this->filters);
    }

    /** @return array<string, int|float|null> */
    private function kpi(): array
    {
        $row = $this->students()->selectRaw("
            count(*) as total,
            count(*) filter (where status_code = '11') as studying,
            count(*) filter (where status_code in ('14','18')) as graduated,
            count(*) filter (where status_code = '12') as expelled,
            count(*) filter (where status_code = '13') as academic_leave,
            count(*) filter (where status_code = '15') as repeating,
            count(*) filter (where status_code = '16') as mobility,
            count(*) filter (where status_code = '11' and payment_form_code = '11') as studying_grant,
            count(*) filter (where status_code = '11' and gender_code = '12') as studying_female,
            count(*) filter (where status_code = '11' and education_type_code = '12') as studying_master,
            count(distinct group_id) filter (where status_code = '11') as active_groups,
            count(distinct specialty_id) filter (where status_code = '11') as active_specialties
        ")->first();

        $diplomas = $this->diplomas()->selectRaw("
            count(*) as total,
            count(*) filter (where category_name ilike '%imtiyoz%') as honors,
            round((avg(avg_grade) filter (where avg_grade between 2 and 5))::numeric, 2) as avg_grade
        ")->first();

        $latestYear = $this->gpaQuery()->max('education_year_code');
        $gpa = $this->gpaQuery()->where('student_gpas.education_year_code', $latestYear)
            ->selectRaw('round(avg(gpa)::numeric, 2) as avg_gpa')->value('avg_gpa');

        $studying = max((int) $row->studying, 1);

        return [
            'total' => (int) $row->total,
            'studying' => (int) $row->studying,
            'graduated' => (int) $row->graduated,
            'expelled' => (int) $row->expelled,
            'academic_leave' => (int) $row->academic_leave,
            'repeating' => (int) $row->repeating,
            'mobility' => (int) $row->mobility,
            'master' => (int) $row->studying_master,
            'active_groups' => (int) $row->active_groups,
            'active_specialties' => (int) $row->active_specialties,
            'grant_share' => round($row->studying_grant / $studying * 100, 1),
            'female_share' => round($row->studying_female / $studying * 100, 1),
            'diplomas' => (int) $diplomas->total,
            'honors' => (int) $diplomas->honors,
            'diploma_avg_grade' => $diplomas->avg_grade !== null ? (float) $diplomas->avg_grade : null,
            'avg_gpa' => $gpa !== null ? (float) $gpa : null,
            'gpa_year' => $latestYear,
        ];
    }

    /**
     * Ustun bo'yicha guruhlash: [{label, value}].
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function breakdown(Builder $query, string $column, ?int $limit = null): array
    {
        $rows = $query->selectRaw("coalesce(nullif(trim({$column}), ''), 'Ko''rsatilmagan') as label, count(*) as value")
            ->groupBy('label')->orderByDesc('value')
            ->when($limit, fn (Builder $q, $l) => $q->limit($l))
            ->get();

        return [
            'labels' => $rows->pluck('label')->all(),
            'values' => $rows->pluck('value')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * Qabul yillari bo'yicha kogortalar va ularning hozirgi holati.
     *
     * @return array{years: array<int, int>, series: array<int, array{name: string, data: array<int, int>}>}
     */
    private function cohorts(): array
    {
        $rows = $this->students()->whereNotNull('year_of_enter')->where('year_of_enter', '>', 1990)
            ->selectRaw("year_of_enter as year,
                count(*) filter (where status_code = '11') as studying,
                count(*) filter (where status_code in ('14','18')) as graduated,
                count(*) filter (where status_code = '12') as expelled,
                count(*) filter (where status_code in ('13','15','16')) as paused,
                count(*) filter (where status_code not in ('11','12','13','14','15','16','18') or status_code is null) as other")
            ->groupBy('year')->orderBy('year')->get();

        $series = [
            'studying' => 'O‘qimoqda',
            'graduated' => 'Bitirgan',
            'expelled' => 'Chetlashgan',
            'paused' => 'Ta’til / kursdan qolgan / mobil',
            'other' => 'Boshqa',
        ];

        return [
            'years' => $rows->pluck('year')->map(fn ($v) => (int) $v)->all(),
            'series' => collect($series)->map(fn ($name, $key) => [
                'name' => $name,
                'data' => $rows->pluck($key)->map(fn ($v) => (int) $v)->all(),
            ])->values()->all(),
        ];
    }

    /** @return array{years: array, total: array, honors: array, avg_grade: array} */
    private function graduatesByYear(): array
    {
        $rows = $this->diplomas()->whereNotNull('graduate_year')
            ->selectRaw("graduate_year as year, count(*) as total,
                count(*) filter (where category_name ilike '%imtiyoz%') as honors,
                round((avg(avg_grade) filter (where avg_grade between 2 and 5))::numeric, 2) as avg_grade")
            ->groupBy('year')->orderBy('year')->get();

        return [
            'years' => $rows->pluck('year')->map(fn ($v) => (int) $v)->all(),
            'total' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
            'honors' => $rows->pluck('honors')->map(fn ($v) => (int) $v)->all(),
            'avg_grade' => $rows->pluck('avg_grade')->map(fn ($v) => $v !== null ? (float) $v : null)->all(),
        ];
    }

    /** @return array{labels: array, male: array, female: array} */
    private function faculties(): array
    {
        $rows = $this->studying()
            ->selectRaw("coalesce(department_name, 'Ko''rsatilmagan') as label,
                count(*) filter (where gender_code = '11') as male,
                count(*) filter (where gender_code = '12') as female,
                count(*) as total")
            ->groupBy('label')->orderByDesc('total')->get();

        return [
            'labels' => $rows->pluck('label')->all(),
            'male' => $rows->pluck('male')->map(fn ($v) => (int) $v)->all(),
            'female' => $rows->pluck('female')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** Kurslar kesimida, ta'lim shakli bo'yicha bo'lingan. */
    private function levels(): array
    {
        $rows = $this->studying()->whereNotNull('level_name')
            ->selectRaw("level_code, level_name, coalesce(education_form_name, 'Ko''rsatilmagan') as form, count(*) as value")
            ->groupBy('level_code', 'level_name', 'form')->orderBy('level_code')->get();

        $labels = $rows->pluck('level_name')->unique()->values();
        $forms = $rows->groupBy('form')->sortByDesc(fn ($g) => $g->sum('value'));

        return [
            'labels' => $labels->all(),
            'series' => $forms->map(fn ($group, $form) => [
                'name' => $form,
                'data' => $labels->map(fn ($l) => (int) $group->where('level_name', $l)->sum('value'))->all(),
            ])->values()->all(),
        ];
    }

    /** @return array{labels: array, values: array} */
    private function ages(): array
    {
        $rows = $this->studying()->whereNotNull('birth_date')
            ->selectRaw("case
                when date_part('year', age(birth_date)) < 18 then '17 va kichik'
                when date_part('year', age(birth_date)) <= 19 then '18–19'
                when date_part('year', age(birth_date)) <= 21 then '20–21'
                when date_part('year', age(birth_date)) <= 23 then '22–23'
                when date_part('year', age(birth_date)) <= 26 then '24–26'
                when date_part('year', age(birth_date)) <= 30 then '27–30'
                when date_part('year', age(birth_date)) <= 40 then '31–40'
                else '40+' end as label,
                min(date_part('year', age(birth_date))) as sort, count(*) as value")
            ->groupBy('label')->orderBy('sort')->get();

        return [
            'labels' => $rows->pluck('label')->all(),
            'values' => $rows->pluck('value')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    private function gpaQuery(): Builder
    {
        $query = DB::table('student_gpas')->whereNotNull('gpa')->where('gpa', '>', 0)->where('gpa', '<=', 5);
        if ($this->hasFilters()) {
            $query->whereIn('student_gpas.student_hemis_id', $this->students()->select('hemis_id'));
        }

        return $query;
    }

    /** O'quv yillari bo'yicha o'rtacha GPA va qarzdorlar ulushi. */
    private function gpaByYear(): array
    {
        $rows = $this->gpaQuery()
            ->selectRaw('education_year_code as code, education_year_name as year, round(avg(gpa)::numeric, 2) as avg_gpa,
                round(100.0 * count(*) filter (where debt_subjects > 0) / count(*), 1) as debt_share,
                count(*) as total')
            ->groupBy('education_year_code', 'education_year_name')->orderBy('education_year_code')->get();

        return [
            'years' => $rows->pluck('year')->all(),
            'codes' => $rows->pluck('code')->all(),
            'avg_gpa' => $rows->pluck('avg_gpa')->map(fn ($v) => (float) $v)->all(),
            'debt_share' => $rows->pluck('debt_share')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    /** So'nggi o'quv yili GPA taqsimoti (0.25 qadam). */
    private function gpaDistribution(): array
    {
        $latestYear = $this->gpaQuery()->max('education_year_code');
        $rows = $this->gpaQuery()->where('education_year_code', $latestYear)
            ->selectRaw('floor(gpa * 4) / 4 as bucket, count(*) as value')
            ->groupBy('bucket')->orderBy('bucket')->get();

        return [
            'labels' => $rows->pluck('bucket')->map(fn ($b) => number_format((float) $b, 2))->all(),
            'values' => $rows->pluck('value')->map(fn ($v) => (int) $v)->all(),
            'year_code' => $latestYear,
        ];
    }

    /** Ma'lumotlar sifati ko'rsatkichlari (PDF 12-bo'lim). */
    private function quality(): array
    {
        return app(DataQuality::class)->withFilters($this->filters)->summary();
    }

    /** @return array<int, array{entity: string, status: string, records: int, finished_at: ?string}> */
    private function lastSync(): array
    {
        return SyncLog::query()
            ->whereIn('id', SyncLog::query()->selectRaw('max(id)')->groupBy('entity'))
            ->orderBy('entity')->get()
            ->map(fn (SyncLog $log) => [
                'entity' => $log->entity,
                'status' => $log->status,
                'records' => $log->records,
                'finished_at' => $log->finished_at?->format('d.m.Y H:i'),
            ])->all();
    }

    /** @return array<int, array{code: string, name: string}> */
    private function distinct(string $prefix): array
    {
        return DB::table('students')->whereNotNull($prefix.'_code')
            ->select($prefix.'_code as code', $prefix.'_name as name')
            ->distinct()->orderBy('code')->get()
            ->map(fn ($row) => (array) $row)->all();
    }
}
