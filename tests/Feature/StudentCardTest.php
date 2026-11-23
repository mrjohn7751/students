<?php

namespace Tests\Feature;

use App\Models\Diploma;
use App\Models\Student;
use App\Models\StudentGpa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /** @param  array<string, mixed>  $attributes */
    private function student(int $hemisId, array $attributes = []): Student
    {
        return Student::query()->create([
            'hemis_id' => $hemisId,
            'student_id_number' => '35121'.$hemisId,
            'full_name' => "TALABA {$hemisId}",
            'birth_date' => '2002-01-01',
            'status_code' => Student::STATUS_STUDYING,
            'status_name' => 'O‘qimoqda',
            'department_id' => 5,
            'department_name' => 'Filologiya fakulteti',
            'group_name' => '101-21',
            'year_of_enter' => 2021,
            ...$attributes,
        ]);
    }

    public function test_index_searches_by_name_and_filters_by_status(): void
    {
        $this->student(1, ['full_name' => 'KARIMOV ALI']);
        $this->student(2, ['full_name' => 'KARIMOVA DILNOZA', 'status_code' => Student::STATUS_GRADUATED, 'status_name' => 'Bitirgan']);
        $this->student(3, ['full_name' => 'TOSHEV BEK']);

        $this->get(route('students.index', ['q' => 'karimov']))
            ->assertOk()
            ->assertSee('KARIMOV ALI')
            ->assertSee('KARIMOVA DILNOZA')
            ->assertDontSee('TOSHEV BEK');

        $this->get(route('students.index', ['q' => 'karimov', 'status' => Student::STATUS_GRADUATED]))
            ->assertOk()
            ->assertSee('KARIMOVA DILNOZA')
            ->assertDontSee('KARIMOV ALI');
    }

    public function test_card_shows_history_diploma_and_related_records(): void
    {
        $student = $this->student(10, ['full_name' => 'ALIYEVA MADINA', 'status_name' => 'Bitirgan']);
        $this->student(11, ['full_name' => 'ALIYEVA MADINA', 'education_type_name' => 'Magistr']);
        $this->student(12, ['full_name' => 'ALIYEVA MADINA', 'birth_date' => '1999-05-05']);

        StudentGpa::query()->create(['hemis_id' => 500, 'student_hemis_id' => 10, 'education_year_name' => '2021-2022', 'level_name' => '1-kurs', 'gpa' => 4.25]);
        Diploma::query()->create(['hemis_id' => 900, 'student_hemis_id' => 10, 'diploma_number' => 'B1234567', 'graduate_date' => '2025-06-30']);

        $this->get(route('students.show', $student->hemis_id))
            ->assertOk()
            ->assertSee('ALIYEVA MADINA')
            ->assertSee('2021-2022')
            ->assertSee('4.25')
            ->assertSee('B1234567')
            ->assertSee('Buyruqlar')
            ->assertSee(route('students.show', 11))
            ->assertDontSee(route('students.show', 12));
    }

    public function test_index_accepts_several_status_codes(): void
    {
        $this->student(1, ['full_name' => 'BITIRGAN BIR', 'status_code' => '14']);
        $this->student(2, ['full_name' => 'BITIRGAN IKKI', 'status_code' => '18']);
        $this->student(3, ['full_name' => 'OQIYOTGAN UCH']);

        $this->get(route('students.index', ['status' => '14,18']))
            ->assertOk()
            ->assertSee('BITIRGAN BIR')
            ->assertSee('BITIRGAN IKKI')
            ->assertDontSee('OQIYOTGAN UCH');

        $this->get(route('students.index', ['status' => '14;drop']))->assertSessionHasErrors('status');
    }

    public function test_dashboard_drill_down_filters(): void
    {
        $this->student(1, ['full_name' => 'GPA YUQORI', 'province_name' => 'Jizzax viloyati']);
        $this->student(2, ['full_name' => 'GPA PAST', 'province_name' => null]);
        $this->student(3, ['full_name' => 'BITIRGAN IMTIYOZ', 'status_code' => '14', 'birth_date' => now()->subYears(29)->toDateString()]);
        StudentGpa::query()->create(['hemis_id' => 1, 'student_hemis_id' => 1, 'education_year_code' => '2026', 'gpa' => 3.80]);
        StudentGpa::query()->create(['hemis_id' => 2, 'student_hemis_id' => 2, 'education_year_code' => '2026', 'gpa' => 3.10, 'debt_subjects' => 2]);
        Diploma::query()->create(['hemis_id' => 9, 'student_hemis_id' => 3, 'graduate_year' => 2025, 'category_name' => 'Imtiyozli']);

        $only = fn (array $params, string $expected) => $this->get(route('students.index', $params))->assertOk()
            ->assertSee($expected)
            ->assertDontSee(collect(['GPA YUQORI', 'GPA PAST', 'BITIRGAN IMTIYOZ'])->reject(fn ($n) => $n === $expected)->all());

        $only(['gpa_year' => '2026', 'gpa_min' => '3.75', 'gpa_max' => '4.00'], 'GPA YUQORI');
        $only(['gpa_year' => '2026', 'debt' => 1], 'GPA PAST');
        $only(['status' => '11', 'f' => ['province_name' => 'Jizzax viloyati']], 'GPA YUQORI');
        $only(['f' => ['province_name' => "Ko'rsatilmagan"], 'status' => '11'], 'GPA PAST');
        $only(['graduate_year' => 2025, 'diploma' => 'honors'], 'BITIRGAN IMTIYOZ');
        $only(['age_min' => 27, 'age_max' => 30], 'BITIRGAN IMTIYOZ');

        $this->get(route('students.index', ['gpa_min' => '3.75']))->assertSee('GPA:');
        $this->get(route('students.index', ['f' => ['full_name; drop table students' => 'x']]))->assertSessionHasErrors('f');
    }

    public function test_unknown_student_returns_404(): void
    {
        $this->get(route('students.show', 999999))->assertNotFound();
    }
}
