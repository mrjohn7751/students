<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HemisSyncDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        config(['hemis.token' => 'test-token', 'hemis.url' => 'https://hemis.test/rest/v1', 'hemis.throttle_us' => 0]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function page(array $items): array
    {
        return ['success' => true, 'error' => null, 'data' => [
            'items' => $items,
            'pagination' => ['totalCount' => count($items), 'pageSize' => 200, 'pageCount' => 1, 'page' => 1],
        ]];
    }

    /** @return array<string, mixed> */
    private function student(int $id, string $statusCode, string $genderCode): array
    {
        return [
            'id' => $id,
            'full_name' => "TALABA {$id}",
            'birth_date' => 1009843200,
            'gender' => ['code' => $genderCode, 'name' => $genderCode === '11' ? 'Erkak' : 'Ayol'],
            'studentStatus' => ['code' => $statusCode, 'name' => $statusCode === '11' ? 'O‘qimoqda' : 'Bitirgan'],
            'paymentForm' => ['code' => '11', 'name' => 'Davlat granti'],
            'educationType' => ['code' => '11', 'name' => 'Bakalavr'],
            'educationForm' => ['code' => '11', 'name' => 'Kunduzgi'],
            'department' => ['id' => 5, 'name' => 'Filologiya fakulteti'],
            'specialty' => ['id' => 7, 'code' => '60110700', 'name' => 'O‘zbek tili'],
            'group' => ['id' => 9, 'name' => '101-21', 'educationLang' => ['code' => '11', 'name' => 'O‘zbek']],
            'level' => ['code' => '11', 'name' => '1-kurs'],
            'year_of_enter' => 2021,
            'created_at' => 1700000000,
        ];
    }

    private function fakeHemis(): void
    {
        Http::fake([
            'hemis.test/rest/v1/data/student-list*' => Http::response($this->page([
                $this->student(1, '11', '11'),
                $this->student(2, '11', '12'),
                $this->student(3, '14', '12'),
            ])),
            'hemis.test/rest/v1/data/diploma-list*' => Http::response($this->page([[
                'id' => 100, '_student' => 3, 'diploma_number' => 'B0001',
                'diplomaCategory' => ['code' => '12', 'name' => 'Imtiyozli'],
                'enroll_year' => '01.09.2021', 'graduate_year' => '30.06.2025', 'avg_grade' => 4.8,
            ]])),
            'hemis.test/rest/v1/data/*' => Http::response($this->page([])),
        ]);
    }

    public function test_sync_imports_students_and_diplomas_without_duplicates(): void
    {
        $this->fakeHemis();

        $this->artisan('hemis:sync')->assertSuccessful();
        $this->artisan('hemis:sync')->assertSuccessful();

        $this->assertSame(3, DB::table('students')->count());
        $this->assertSame(1, DB::table('diplomas')->count());
        $this->assertSame(2025, DB::table('diplomas')->value('graduate_year'));
        $this->assertSame('2002-01-01', DB::table('students')->where('hemis_id', 1)->value('birth_date'));
    }

    public function test_dashboard_shows_registry_statistics(): void
    {
        $this->fakeHemis();
        $this->artisan('hemis:sync')->assertSuccessful();

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Reyestrdagi jami shaxslar')
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['kpi']['total'] === 3
                    && $stats['kpi']['studying'] === 2
                    && $stats['kpi']['graduated'] === 1
                    && $stats['kpi']['honors'] === 1
                    && $stats['kpi']['female_share'] === 50.0;
            });
    }

    public function test_dashboard_filters_by_department(): void
    {
        $this->fakeHemis();
        $this->artisan('hemis:sync')->assertSuccessful();

        $this->get(route('dashboard', ['department_id' => 999]))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['kpi']['total'] === 0);
    }

    public function test_quality_pages_list_duplicates_and_export_csv(): void
    {
        Http::fake([
            'hemis.test/rest/v1/data/student-list*' => Http::response($this->page([
                $this->student(1, '14', '12'),
                ['full_name' => 'talaba 1'] + $this->student(2, '11', '12'),
                $this->student(3, '11', '11'),
            ])),
            'hemis.test/rest/v1/data/*' => Http::response($this->page([])),
        ]);
        $this->artisan('hemis:sync')->assertSuccessful();

        $this->get(route('quality.show', 'duplicates'))
            ->assertOk()
            ->assertSee('TALABA 1')
            ->assertSee('2 ta yozuv');

        $this->get(route('quality.show', 'graduates_without_diploma'))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1);

        $csv = $this->get(route('quality.export', 'duplicates'))->assertOk()->streamedContent();
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1); // sarlavha + 2 yozuv

        $this->get(route('quality.show', 'unknown'))->assertNotFound();
    }
}
