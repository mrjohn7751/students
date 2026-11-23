<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Services\Hemis\HemisClient;
use App\Services\Stats\DashboardStats;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * HEMIS'dan reyestr ma'lumotlarini olib, PostgreSQL'ga upsert qiladi.
 * Takroriy ishga tushirish dublikat yaratmaydi (hemis_id bo'yicha upsert).
 */
class HemisSync extends Command
{
    protected $signature = 'hemis:sync
        {--only=* : Faqat tanlangan obyektlar: departments, specialties, groups, students, diplomas, gpas}';

    protected $description = 'HEMIS API\'dan bo\'linmalar, yo\'nalishlar, guruhlar, talabalar, diplomlar va GPA ma\'lumotlarini sinxronlash';

    /**
     * Har bir obyekt: [endpoint, jadval, unique kalit, mapper, parametrlar to'plamlari].
     * Bir nechta parametr to'plami berilsa, ma'lumot bo'laklab olinadi — HEMIS sahifalashi
     * katta ro'yxatlarda barqaror emas (sahifalar takrorlanib, yozuvlar tushib qoladi).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: callable, 4: array<int, array>|callable}>
     */
    private function entities(): array
    {
        return [
            'departments' => ['department-list', 'departments', 'id', $this->mapDepartment(...), [[]]],
            'specialties' => ['specialty-list', 'specialties', 'id', $this->mapSpecialty(...), [[]]],
            'groups' => ['group-list', 'groups', 'id', $this->mapGroup(...), [[]]],
            // _student_status=-1 — barcha statusdagi (shu jumladan sobiq) talabalar
            'students' => ['student-list', 'students', 'hemis_id', $this->mapStudent(...), [['_student_status' => -1]]],
            'diplomas' => ['diploma-list', 'diplomas', 'hemis_id', $this->mapDiploma(...), $this->diplomaPartitions(...)],
            'gpas' => ['student-gpa-list', 'student_gpas', 'hemis_id', $this->mapGpa(...), [[]]],
        ];
    }

    public function handle(HemisClient $client, DashboardStats $stats): int
    {
        $only = $this->option('only');
        $failed = false;

        foreach ($this->entities() as $name => [$endpoint, $table, $key, $mapper, $partitions]) {
            if ($only && ! in_array($name, $only, true)) {
                continue;
            }
            $partitions = is_callable($partitions) ? $partitions() : $partitions;
            $failed = ! $this->syncEntity($client, $name, $endpoint, $table, $key, $mapper, $partitions) || $failed;
        }

        $stats->flush();

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * diploma-list sahifalashi barqaror emas (bir xil yozuv bir necha sahifada qaytadi).
     * Har bir guruh diplomlari bitta sahifaga sig'adi, shuning uchun guruhlar bo'yicha olinadi;
     * guruhi ro'yxatda bo'lmagan diplomlar uchun o'quv yillari bo'yicha qo'shimcha o'tish qilinadi.
     *
     * @return array<int, array<string, int>>
     */
    private function diplomaPartitions(): array
    {
        $groups = DB::table('groups')->orderBy('id')->pluck('id')
            ->map(fn (int $id) => ['_group' => $id])->all();
        $years = array_map(fn (int $year) => ['_education_year' => $year], range(2000, (int) now()->year + 1));

        return [...$groups, ...$years];
    }

    /**
     * @param  array<int, array<string, mixed>>  $partitions
     */
    private function syncEntity(HemisClient $client, string $name, string $endpoint, string $table, string $key, callable $mapper, array $partitions): bool
    {
        $log = SyncLog::create(['entity' => $name, 'started_at' => now()]);
        $this->info("▶ {$name} ({$endpoint}".(count($partitions) > 1 ? ', '.count($partitions).' bo\'lak' : '').')');
        $bar = $this->output->createProgressBar();
        $bar->start();
        $pagesDone = 0;

        try {
            foreach ($partitions as $params) {
                foreach ($client->pages($endpoint, $params) as $chunk) {
                    if ($chunk['page'] === 1) {
                        $bar->setMaxSteps($bar->getMaxSteps() + $chunk['pageCount']);
                    }

                    $now = now();
                    $rows = array_map(fn (array $item) => $mapper($item) + ['created_at' => $now, 'updated_at' => $now], $chunk['items']);
                    if ($rows) {
                        $rows = array_values(array_column($rows, null, $key)); // sahifa ichidagi takrorlar
                        $updateColumns = array_values(array_diff(array_keys($rows[0]), [$key, 'created_at']));
                        DB::table($table)->upsert($rows, [$key], $updateColumns);
                    }

                    $log->increment('records', count($rows), [
                        'pages_done' => ++$pagesDone,
                        'pages_total' => max($bar->getMaxSteps(), $pagesDone),
                    ]);
                    $bar->advance();
                }
            }

            $bar->finish();
            $log->update(['records' => DB::table($table)->count()]);
            $this->newLine();
            $log->update(['status' => 'success', 'finished_at' => now()]);
            $this->line("  ✔ {$log->records} ta yozuv");

            return true;
        } catch (Throwable $e) {
            $this->newLine();
            $log->update(['status' => 'error', 'error' => $e->getMessage(), 'finished_at' => now()]);
            $this->error("  ✘ {$name}: ".$e->getMessage());

            return false;
        }
    }

    private function mapDepartment(array $d): array
    {
        return [
            'id' => $d['id'],
            'name' => trim($d['name']),
            'code' => $d['code'] ?? null,
            'structure_type_code' => $d['structureType']['code'] ?? null,
            'structure_type_name' => $d['structureType']['name'] ?? null,
            'parent_id' => $d['parent'] ?? null,
            'active' => (bool) ($d['active'] ?? true),
        ];
    }

    private function mapSpecialty(array $s): array
    {
        return [
            'id' => $s['id'],
            'code' => $s['code'] ?? null,
            'name' => trim($s['name']),
            'department_id' => $s['department']['id'] ?? null,
            'education_type_code' => $s['educationType']['code'] ?? null,
            'education_type_name' => $s['educationType']['name'] ?? null,
            'active' => (bool) ($s['active'] ?? true),
        ];
    }

    private function mapGroup(array $g): array
    {
        return [
            'id' => $g['id'],
            'name' => trim($g['name']),
            'department_id' => $g['department']['id'] ?? null,
            'specialty_id' => $g['specialty']['id'] ?? null,
            'education_lang_name' => $g['educationLang']['name'] ?? null,
            'curriculum_id' => $g['_curriculum'] ?? null,
            'active' => (bool) ($g['active'] ?? true),
        ];
    }

    private function mapStudent(array $s): array
    {
        $classifiers = [
            'gender' => $s['gender'] ?? null,
            'country' => $s['country'] ?? null,
            'province' => $s['province'] ?? null,
            'district' => $s['district'] ?? null,
            'citizenship' => $s['citizenship'] ?? null,
            'status' => $s['studentStatus'] ?? null,
            'education_form' => $s['educationForm'] ?? null,
            'education_type' => $s['educationType'] ?? null,
            'payment_form' => $s['paymentForm'] ?? null,
            'grant_type' => $s['grantType'] ?? null,
            'student_type' => $s['studentType'] ?? null,
            'social_category' => $s['socialCategory'] ?? null,
            'accommodation' => $s['accommodation'] ?? null,
            'level' => $s['level'] ?? null,
            'education_year' => $s['educationYear'] ?? null,
            'education_lang' => $s['group']['educationLang'] ?? null,
            'admission_type' => $s['admissionType'] ?? null,
            'transfer_type' => $s['transferType'] ?? null,
            'academic_mobile_type' => $s['academicMobileType'] ?? null,
        ];

        $row = [
            'hemis_id' => $s['id'],
            'meta_id' => $s['meta_id'] ?? null,
            'student_id_number' => $s['student_id_number'] ?? null,
            'full_name' => trim((string) ($s['full_name'] ?? '')),
            'first_name' => $s['first_name'] ?? null,
            'second_name' => $s['second_name'] ?? null,
            'third_name' => $s['third_name'] ?? null,
            'birth_date' => $this->date($s['birth_date'] ?? null),
            'image' => $s['image'] ?? null,
            'department_id' => $s['department']['id'] ?? null,
            'department_name' => isset($s['department']['name']) ? trim($s['department']['name']) : null,
            'specialty_id' => $s['specialty']['id'] ?? null,
            'specialty_code' => $s['specialty']['code'] ?? null,
            'specialty_name' => isset($s['specialty']['name']) ? trim($s['specialty']['name']) : null,
            'group_id' => $s['group']['id'] ?? null,
            'group_name' => $s['group']['name'] ?? null,
            'curriculum_id' => $s['_curriculum'] ?? null,
            'year_of_enter' => $s['year_of_enter'] ?? null,
            'avg_gpa' => $s['avg_gpa'] ?? null,
            'is_graduate' => (bool) ($s['is_graduate'] ?? false),
            'transfer_university' => is_string($s['transfer_university'] ?? null) ? $s['transfer_university'] : null,
            'hemis_created_at' => $this->timestamp($s['created_at'] ?? null),
            'hemis_updated_at' => $this->timestamp($s['updated_at'] ?? null),
            'raw' => json_encode($s, JSON_UNESCAPED_UNICODE),
        ];

        foreach ($classifiers as $prefix => $value) {
            $row[$prefix.'_code'] = isset($value['code']) ? (string) $value['code'] : null;
            $row[$prefix.'_name'] = $value['name'] ?? null;
        }

        return $row;
    }

    private function mapDiploma(array $d): array
    {
        $graduateDate = $this->dotDate($d['graduate_year'] ?? null);

        return [
            'hemis_id' => $d['id'],
            'uid' => $d['uid'] ?? null,
            'student_hemis_id' => $d['_student'] ?? null,
            'diploma_number' => $d['diploma_number'] ?? null,
            'register_number' => $d['register_number'] ?? null,
            'register_date' => $this->date($d['register_date'] ?? null),
            'avg_grade' => $d['avg_grade'] ?? null,
            'department_id' => $d['department']['id'] ?? null,
            'department_name' => isset($d['department']['name']) ? trim($d['department']['name']) : null,
            'group_name' => $d['group']['name'] ?? null,
            'specialty_code' => $d['specialty']['code'] ?? null,
            'specialty_name' => isset($d['specialty']['name']) ? trim($d['specialty']['name']) : null,
            'education_type_code' => $d['educationType']['code'] ?? null,
            'education_type_name' => $d['educationType']['name'] ?? null,
            'education_form_code' => $d['educationForm']['code'] ?? null,
            'education_form_name' => $d['educationForm']['name'] ?? null,
            'category_code' => $d['diplomaCategory']['code'] ?? null,
            'category_name' => $d['diplomaCategory']['name'] ?? null,
            'enroll_date' => $this->dotDate($d['enroll_year'] ?? null),
            'graduate_date' => $graduateDate,
            'graduate_year' => $graduateDate ? (int) substr($graduateDate, 0, 4) : null,
            'education_period' => is_numeric($d['education_period'] ?? null) ? (int) $d['education_period'] : null,
            'accepted' => (bool) ($d['accepted'] ?? false),
            'published' => (bool) ($d['published'] ?? false),
            'diploma_file' => $d['diploma_file'] ?? null,
            'raw' => json_encode($d, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function mapGpa(array $g): array
    {
        return [
            'hemis_id' => $g['id'],
            'student_hemis_id' => $g['_student'] ?? null,
            'group_id' => $g['_group'] ?? null,
            'education_year_code' => $g['educationYear']['code'] ?? null,
            'education_year_name' => $g['educationYear']['name'] ?? null,
            'level_code' => $g['level']['code'] ?? null,
            'level_name' => $g['level']['name'] ?? null,
            'gpa' => is_numeric($g['gpa'] ?? null) ? min((float) $g['gpa'], 99) : null,
            'credit_sum' => is_numeric($g['credit_sum'] ?? null) ? (float) $g['credit_sum'] : null,
            'subjects' => $g['subjects'] ?? null,
            'debt_subjects' => $g['debt_subjects'] ?? null,
            'can_transfer' => $g['can_transfer'] ?? null,
        ];
    }

    /** Unix timestamp → Y-m-d (HEMIS sanalari UTC yarim tunda keladi). */
    private function date(mixed $value): ?string
    {
        return is_numeric($value) && $value > 0 ? Carbon::createFromTimestampUTC((int) $value)->toDateString() : null;
    }

    private function timestamp(mixed $value): ?string
    {
        return is_numeric($value) && $value > 0 ? Carbon::createFromTimestamp((int) $value)->toDateTimeString() : null;
    }

    /** "08.06.2024" → "2024-06-08" */
    private function dotDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim($value), $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
    }
}
