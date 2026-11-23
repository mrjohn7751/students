<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::query()->create(['hemis_id' => 77, 'full_name' => 'MAXFIY TALABA', 'birth_date' => '2003-03-03']);
    }

    public function test_guests_are_redirected_from_every_page(): void
    {
        $student = $this->student();

        foreach ([
            route('dashboard'),
            route('students.index'),
            route('students.show', $student->hemis_id),
            route('quality.show', 'duplicates'),
            route('quality.export', 'duplicates'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_user_can_log_in_and_out_and_both_are_audited(): void
    {
        $user = User::factory()->create(['email' => 'xodim@jdpu.uz']);

        $this->post(route('login.store'), ['email' => 'XODIM@jdpu.uz', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->assertSame(['auth.login', 'auth.logout'], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_wrong_password_and_blocked_user_get_the_same_generic_error(): void
    {
        User::factory()->create(['email' => 'faol@jdpu.uz']);
        User::factory()->inactive()->create(['email' => 'blok@jdpu.uz']);

        foreach ([['faol@jdpu.uz', 'notogri'], ['blok@jdpu.uz', 'password'], ['yoq@jdpu.uz', 'password']] as [$email, $password]) {
            $this->from(route('login'))->post(route('login.store'), ['email' => $email, 'password' => $password])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['email' => 'Email yoki parol noto‘g‘ri.']);
            $this->assertGuest();
        }
        $this->assertSame(3, AuditLog::query()->where('action', 'auth.failed')->count());
    }

    public function test_account_is_locked_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'xodim@jdpu.uz']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => 'xodim@jdpu.uz', 'password' => 'xato']);
        }

        $this->post(route('login.store'), ['email' => 'xodim@jdpu.uz', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertTrue(AuditLog::query()->where('action', 'auth.lockout')->exists());
    }

    public function test_blocked_user_session_is_terminated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_viewer_sees_only_statistics(): void
    {
        $student = $this->student();
        $this->actingAs(User::factory()->role(UserRole::Viewer)->create());

        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('students.index'));
        $this->get(route('students.index'))->assertForbidden();
        $this->get(route('students.show', $student->hemis_id))->assertForbidden();
        $this->get(route('quality.show', 'duplicates'))->assertForbidden();
        $this->get(route('quality.export', 'duplicates'))->assertForbidden();
    }

    public function test_viewing_a_student_and_exporting_are_audited(): void
    {
        $student = $this->student();
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('students.show', $student->hemis_id))->assertOk();
        $this->get(route('quality.export', 'duplicates'))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'student.view', 'subject' => '77']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'quality.export', 'subject' => 'duplicates']);
    }

    public function test_security_headers_are_sent_and_personal_pages_are_not_cached(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->actingAs(User::factory()->create());
        $response = $this->get(route('students.index'))->assertOk();
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_student_pages_are_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 0; $i < 90; $i++) {
            $this->get(route('students.index'));
        }

        $this->get(route('students.index'))->assertTooManyRequests();
    }

    public function test_user_create_command_rejects_weak_passwords(): void
    {
        $this->artisan('user:create', ['email' => 'admin@jdpu.uz', '--name' => 'Admin', '--role' => 'admin'])
            ->expectsQuestion('Parol (kamida 12 belgi, katta-kichik harf va raqam)', 'qisqa')
            ->expectsQuestion('Parolni takrorlang', 'qisqa')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'admin@jdpu.uz']);

        $this->artisan('user:create', ['email' => 'admin@jdpu.uz', '--name' => 'Admin', '--role' => 'admin', '--generate-password' => true])
            ->assertSuccessful();
        $this->assertSame(UserRole::Admin, User::query()->where('email', 'admin@jdpu.uz')->first()->role);
    }
}
