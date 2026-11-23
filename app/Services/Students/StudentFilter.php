<?php

namespace App\Services\Students;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Talabalar ro'yxati filtrlari. Dashboard grafiklaridan "drill-down" ham shu parametrlar orqali ishlaydi:
 * har bir ustun/bo'lak bosilganda tegishli parametrlar bilan ro'yxat ochiladi.
 */
class StudentFilter
{
    /** Dashboard'dagi breakdown'lar bilan bir xil yorliq — bo'sh qiymatlar shu nom bilan guruhlanadi. */
    public const EMPTY_LABEL = "Ko'rsatilmagan";

    /** Qiymati bo'yicha to'g'ridan-to'g'ri filtrlanadigan klassifikator ustunlari: ustun => sarlavha. */
    public const FIELDS = [
        'status_name' => 'Status',
        'department_name' => 'Fakultet',
        'specialty_name' => 'Yo‘nalish',
        'level_name' => 'Kurs',
        'gender_name' => 'Jins',
        'education_type_name' => 'Ta’lim turi',
        'education_form_name' => 'Ta’lim shakli',
        'education_lang_name' => 'Ta’lim tili',
        'payment_form_name' => 'To‘lov shakli',
        'province_name' => 'Viloyat',
        'citizenship_name' => 'Fuqarolik',
        'accommodation_name' => 'Yashash joyi',
        'social_category_name' => 'Ijtimoiy toifa',
        'admission_type_name' => 'Qabul turi',
    ];

    /** Kogorta chartidagi "Boshqa" guruhi: quyidagilardan tashqari barcha statuslar. */
    private const KNOWN_STATUSES = ['11', '12', '13', '14', '15', '16', '18'];

    /** @var array<string, mixed> */
    private array $filters = [];

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:40', 'regex:/^(\d+(,\d+)*|other)$/'],
            'education_type' => ['nullable', 'string', 'max:8'],
            'education_form' => ['nullable', 'string', 'max:8'],
            'year_of_enter' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'f' => ['nullable', 'array:'.implode(',', array_keys(self::FIELDS))],
            'f.*' => ['nullable', 'string', 'max:255'],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gpa_year' => ['nullable', 'string', 'max:8', 'regex:/^\d+$/'],
            'gpa_min' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'gpa_max' => ['nullable', 'numeric', 'min:0', 'max:6'],
            'debt' => ['nullable', 'boolean'],
            'graduate_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'diploma' => ['nullable', 'string', 'in:honors,regular'],
            'diploma_category' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @param  array<string, mixed>  $validated */
    public function __construct(array $validated = [])
    {
        $this->filters = collect($validated)
            ->except('q')
            ->map(fn ($v) => is_array($v) ? array_filter($v, fn ($x) => $x !== null && $x !== '') : $v)
            ->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])
            ->all();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->filters;
    }

    public function isEmpty(): bool
    {
        return $this->filters === [];
    }

    public function apply(Builder $query): Builder
    {
        $f = $this->filters;

        $query->when($f['department_id'] ?? null, fn (Builder $q, $v) => $q->where('department_id', $v))
            ->when($f['education_type'] ?? null, fn (Builder $q, $v) => $q->where('education_type_code', $v))
            ->when($f['education_form'] ?? null, fn (Builder $q, $v) => $q->where('education_form_code', $v))
            ->when($f['year_of_enter'] ?? null, fn (Builder $q, $v) => $q->where('year_of_enter', $v));

        if (isset($f['status'])) {
            $f['status'] === 'other'
                ? $query->where(fn (Builder $q) => $q->whereNotIn('status_code', self::KNOWN_STATUSES)->orWhereNull('status_code'))
                : $query->whereIn('status_code', explode(',', $f['status']));
        }

        foreach ($f['f'] ?? [] as $column => $value) {
            // $column FIELDS ro'yxatidan validatsiyadan o'tgan — xom SQL'da xavfsiz
            $value === self::EMPTY_LABEL
                ? $query->where(fn (Builder $q) => $q->whereNull($column)->orWhereRaw("trim({$column}) = ''"))
                : $query->whereRaw("trim({$column}) = ?", [$value]);
        }

        if (isset($f['age_min']) || isset($f['age_max'])) {
            $query->whereNotNull('birth_date')
                ->when(isset($f['age_min']), fn (Builder $q) => $q->whereRaw("date_part('year', age(birth_date)) >= ?", [(int) $f['age_min']]))
                ->when(isset($f['age_max']), fn (Builder $q) => $q->whereRaw("date_part('year', age(birth_date)) <= ?", [(int) $f['age_max']]));
        }

        if (isset($f['gpa_year']) || isset($f['gpa_min']) || isset($f['gpa_max']) || ! empty($f['debt'])) {
            $query->whereIn('hemis_id', DB::table('student_gpas')->select('student_hemis_id')
                ->whereNotNull('student_hemis_id')
                ->when($f['gpa_year'] ?? null, fn ($q, $v) => $q->where('education_year_code', $v))
                ->when(isset($f['gpa_min']), fn ($q) => $q->where('gpa', '>=', $f['gpa_min']))
                ->when(isset($f['gpa_max']), fn ($q) => $q->where('gpa', '<', $f['gpa_max']))
                ->when(! empty($f['debt']), fn ($q) => $q->where('debt_subjects', '>', 0)));
        }

        if (isset($f['graduate_year']) || isset($f['diploma']) || isset($f['diploma_category'])) {
            $query->whereIn('hemis_id', DB::table('diplomas')->select('student_hemis_id')
                ->whereNotNull('student_hemis_id')
                ->when($f['graduate_year'] ?? null, fn ($q, $v) => $q->where('graduate_year', $v))
                ->when(($f['diploma'] ?? null) === 'honors', fn ($q) => $q->where('category_name', 'ilike', '%imtiyoz%'))
                ->when(($f['diploma'] ?? null) === 'regular', fn ($q) => $q->where(fn ($w) => $w->whereNull('category_name')->orWhere('category_name', 'not ilike', '%imtiyoz%')))
                ->when($f['diploma_category'] ?? null, fn ($q, $v) => $v === self::EMPTY_LABEL
                    ? $q->where(fn ($w) => $w->whereNull('category_name')->orWhereRaw("trim(category_name) = ''"))
                    : $q->whereRaw('trim(category_name) = ?', [$v])));
        }

        return $query;
    }

    /**
     * Ro'yxat tepasida ko'rsatiladigan faol filtrlar (select'larda ko'rinmaydiganlari).
     *
     * @return array<int, array{label: string, value: string, remove: array<int, string>}>
     */
    public function chips(): array
    {
        $f = $this->filters;
        $chips = [];
        foreach ($f['f'] ?? [] as $column => $value) {
            $chips[] = ['label' => self::FIELDS[$column], 'value' => $value === self::EMPTY_LABEL ? 'Ko‘rsatilmagan' : $value, 'remove' => ["f.{$column}"]];
        }
        if (isset($f['age_min']) || isset($f['age_max'])) {
            $value = match (true) {
                isset($f['age_min'], $f['age_max']) => "{$f['age_min']}–{$f['age_max']} yosh",
                isset($f['age_min']) => "{$f['age_min']} yoshdan katta",
                default => "{$f['age_max']} yosh va kichik",
            };
            $chips[] = ['label' => 'Yosh', 'value' => $value, 'remove' => ['age_min', 'age_max']];
        }
        if (isset($f['gpa_min']) || isset($f['gpa_max'])) {
            $min = isset($f['gpa_min']) ? number_format((float) $f['gpa_min'], 2) : '0.00';
            $max = isset($f['gpa_max']) ? number_format((float) $f['gpa_max'], 2) : '5.00';
            $chips[] = ['label' => 'GPA', 'value' => "{$min} ≤ GPA < {$max}", 'remove' => ['gpa_min', 'gpa_max']];
        }
        if (isset($f['gpa_year'])) {
            $chips[] = ['label' => 'GPA o‘quv yili', 'value' => $f['gpa_year'].'-'.((int) $f['gpa_year'] + 1), 'remove' => ['gpa_year']];
        }
        if (! empty($f['debt'])) {
            $chips[] = ['label' => 'Akademik qarzdorlik', 'value' => 'bor', 'remove' => ['debt']];
        }
        if (isset($f['graduate_year'])) {
            $chips[] = ['label' => 'Bitirgan yili', 'value' => (string) $f['graduate_year'], 'remove' => ['graduate_year']];
        }
        if (isset($f['diploma'])) {
            $chips[] = ['label' => 'Diplom', 'value' => $f['diploma'] === 'honors' ? 'Imtiyozli' : 'Oddiy', 'remove' => ['diploma']];
        }
        if (isset($f['diploma_category'])) {
            $chips[] = ['label' => 'Diplom turi', 'value' => $f['diploma_category'], 'remove' => ['diploma_category']];
        }
        if (($f['status'] ?? null) === 'other') {
            $chips[] = ['label' => 'Status', 'value' => 'Boshqa', 'remove' => ['status']];
        }

        return $chips;
    }
}
