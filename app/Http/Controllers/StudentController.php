<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Diploma;
use App\Models\Student;
use App\Models\StudentGpa;
use App\Services\Stats\DashboardStats;
use App\Services\Students\StudentFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Talabalar ro'yxati (qidiruv) va talabaning yagona kartasi.
 */
class StudentController extends Controller
{
    public function index(Request $request, DashboardStats $stats): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']] + StudentFilter::rules());
        $search = trim($validated['q'] ?? '');
        $filter = new StudentFilter($validated);
        $filters = $filter->all();

        if ($search !== '' || $filters) {
            AuditLog::record('students.search', null, ['q' => $search ?: null, 'filters' => $filters ?: null, 'page' => $request->integer('page', 1)]);
        }

        $students = $filter->apply(Student::query())
            ->when($search !== '', fn (Builder $q) => $this->applySearch($q, $search))
            ->orderBy('full_name')->orderBy('hemis_id')
            ->paginate(50)->withQueryString();

        return view('students.index', [
            'students' => $students,
            'search' => $search,
            'filters' => $filters,
            'chips' => $filter->chips(),
            'options' => $stats->filterOptions() + [
                'statuses' => Student::query()->whereNotNull('status_code')
                    ->select('status_code as code', 'status_name as name')->distinct()->orderBy('status_code')->get()->toArray(),
                'years' => Student::query()->whereNotNull('year_of_enter')
                    ->distinct()->orderByDesc('year_of_enter')->pluck('year_of_enter')->all(),
            ],
        ]);
    }

    public function show(int $hemisId): View
    {
        $student = Student::query()->where('hemis_id', $hemisId)->firstOrFail();
        AuditLog::record('student.view', (string) $student->hemis_id);

        // Shu shaxsning boshqa HEMIS yozuvlari (masalan, bakalavr → magistr, qayta qabul).
        // Avtomatik birlashtirilmaydi — faqat ehtimoliy moslik sifatida ko'rsatiladi.
        $relatedRecords = $student->birth_date
            ? Student::query()
                ->whereRaw('upper(full_name) = upper(?)', [$student->full_name])
                ->whereDate('birth_date', $student->birth_date)
                ->where('hemis_id', '!=', $student->hemis_id)
                ->orderBy('year_of_enter')->get()
            : collect();

        return view('students.show', [
            'student' => $student,
            'raw' => $student->raw ?? [],
            'gpas' => StudentGpa::query()->where('student_hemis_id', $student->hemis_id)
                ->orderBy('education_year_code')->orderBy('level_code')->get(),
            'diplomas' => Diploma::query()->where('student_hemis_id', $student->hemis_id)
                ->orderBy('graduate_date')->get(),
            'relatedRecords' => $relatedRecords,
        ]);
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $term = '%'.mb_strtoupper($search).'%';

        return $query->where(function (Builder $q) use ($search, $term) {
            $q->whereRaw('upper(full_name) like ?', [$term])
                ->orWhere('student_id_number', 'like', '%'.$search.'%')
                ->orWhereRaw('upper(group_name) like ?', [$term]);
            if (ctype_digit($search)) {
                $q->orWhere('hemis_id', (int) $search);
            }
        });
    }
}
