<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Stats\DashboardStats;
use App\Services\Stats\DataQuality;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ma'lumotlar sifati tekshiruvlari bo'yicha to'liq ro'yxatlar.
 */
class QualityController extends Controller
{
    /** CSV ustun sarlavhalari. */
    private const HEADINGS = [
        'hemis_id' => 'HEMIS ID',
        'student_id_number' => 'Talaba ID',
        'full_name' => 'F.I.Sh.',
        'birth_date' => 'Tug‘ilgan sana',
        'gender_name' => 'Jins',
        'status_name' => 'Status',
        'department_name' => 'Fakultet',
        'specialty_name' => 'Yo‘nalish',
        'group_name' => 'Guruh',
        'level_name' => 'Kurs',
        'year_of_enter' => 'Qabul yili',
        'education_type_name' => 'Ta’lim turi',
        'education_form_name' => 'Ta’lim shakli',
        'payment_form_name' => 'To‘lov shakli',
        'province_name' => 'Viloyat',
        'student_hemis_id' => 'Talaba HEMIS ID',
        'diploma_number' => 'Diplom raqami',
        'register_number' => 'Qayd raqami',
        'register_date' => 'Qayd sanasi',
        'category_name' => 'Diplom turi',
        'graduate_year' => 'Bitirgan yili',
        'diploma_file' => 'Diplom fayli',
    ];

    public function show(Request $request, string $check, DataQuality $quality, DashboardStats $stats): View
    {
        abort_unless(isset(DataQuality::CHECKS[$check]), 404);
        [$filters, $search] = $this->input($request);
        $quality = $quality->withFilters($filters);
        $definition = DataQuality::CHECKS[$check];
        AuditLog::record('quality.view', $check, ['q' => $search, 'filters' => $filters ?: null, 'page' => $request->integer('page', 1)]);

        $data = $check === 'duplicates'
            ? $quality->duplicatePage($search)
            : ['rows' => $quality->query($check, $search)->paginate(50)->withQueryString()];

        return view('quality', [
            'check' => $check,
            'definition' => $definition,
            'checks' => $quality->summary()['items'],
            'filters' => $filters,
            'search' => $search,
            'options' => $stats->filterOptions(),
            'isDiploma' => $definition['subject'] === 'diplomas',
            'unavailable' => $check === 'diplomas_without_student' && $quality->hasFilters(),
            ...$data,
        ]);
    }

    public function export(Request $request, string $check, DataQuality $quality): StreamedResponse
    {
        abort_unless(isset(DataQuality::CHECKS[$check]), 404);
        [$filters, $search] = $this->input($request);
        $query = $quality->withFilters($filters)->query($check, $search);
        AuditLog::record('quality.export', $check, ['q' => $search, 'filters' => $filters ?: null]);
        $columns = DataQuality::CHECKS[$check]['subject'] === 'diplomas' ? DataQuality::DIPLOMA_COLUMNS : DataQuality::STUDENT_COLUMNS;

        return response()->streamDownload(function () use ($query, $columns, $check) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel uchun UTF-8 BOM
            $headings = array_map(fn ($c) => self::HEADINGS[$c] ?? $c, $columns);
            if ($check === 'duplicates') {
                array_unshift($headings, 'Guruh №');
            }
            fputcsv($out, $headings, ';');

            $groupNumber = 0;
            $previousKey = null;
            foreach ($query->cursor() as $row) {
                $values = array_map(fn ($c) => $row->{$c}, $columns);
                if ($check === 'duplicates') {
                    $key = mb_strtoupper($row->full_name).'|'.$row->birth_date;
                    $groupNumber += $key !== $previousKey ? 1 : 0;
                    $previousKey = $key;
                    array_unshift($values, $groupNumber);
                }
                fputcsv($out, $values, ';');
            }
            fclose($out);
        }, "jdpu-kontingent-{$check}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function input(Request $request): array
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'education_type' => ['nullable', 'string', 'max:8'],
            'education_form' => ['nullable', 'string', 'max:8'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $search = isset($validated['q']) && trim($validated['q']) !== '' ? trim($validated['q']) : null;
        unset($validated['q']);

        return [array_filter($validated, fn ($v) => $v !== null && $v !== ''), $search];
    }
}
