<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'Kontingent2026Yangi';

    protected function setUp(): void
    {
        parent::setUp();
        // Parol sizib chiqqanini tekshiruvchi tashqi so'rovni testda o'chiramiz
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers());
    }

    public function test_only_admins_can_open_the_panel(): void
    {
        foreach ([UserRole::Staff, UserRole::Viewer] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('admin.users.index'))->assertForbidden();
        }

        $this->actingAs(User::factory()->role(UserRole::Admin)->create())
            ->get(route('admin.users.index'))->assertOk()->assertSee('Foydalanuvchilar');
    }

    public function test_admin_creates_user_who_can_then_log_in_with_username(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->post(route('admin.users.store'), [
            'name' => 'Karimova Dilnoza',
            'username' => ' Dilnoza.K ',
            'role' => 'staff',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('username', 'dilnoza.k')->firstOrFail();
        $this->assertSame(UserRole::Staff, $user->role);
        $this->assertTrue(AuditLog::query()->where('action', 'user.created')->where('subject', 'dilnoza.k')->exists());

        $this->post(route('logout'));
        $this->post(route('login.store'), ['username' => 'dilnoza.k', 'password' => self::STRONG_PASSWORD])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_changes_login_and_password_and_old_sessions_are_closed(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $user = User::factory()->create(['username' => 'eski']);
        DB::table('sessions')->insert(['id' => 'boshqa-qurilma', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => 'yangi',
            'role' => 'viewer',
            'is_active' => '1',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('yangi', $user->username);
        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'boshqa-qurilma']);

        $log = AuditLog::query()->where('action', 'user.updated')->latest('id')->first();
        $this->assertTrue($log->context['password_changed']);
        $this->assertStringNotContainsString(self::STRONG_PASSWORD, json_encode($log->context));
    }

    public function test_empty_password_keeps_the_old_one(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $user = User::factory()->create();
        $oldHash = $user->password;

        $this->put(route('admin.users.update', $user), [
            'name' => 'Yangi ism', 'username' => $user->username, 'role' => 'staff', 'is_active' => '1', 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertSame('Yangi ism', $user->fresh()->name);
    }

    public function test_validation_rejects_weak_password_duplicate_and_invalid_login(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        User::factory()->create(['username' => 'band']);

        $base = ['name' => 'X', 'role' => 'staff', 'password' => self::STRONG_PASSWORD, 'password_confirmation' => self::STRONG_PASSWORD];
        $this->post(route('admin.users.store'), [...$base, 'username' => 'band'])->assertSessionHasErrors('username');
        $this->post(route('admin.users.store'), [...$base, 'username' => 'bo‘sh joy'])->assertSessionHasErrors('username');
        $this->post(route('admin.users.store'), [...$base, 'username' => 'yaxshi', 'password' => 'qisqa', 'password_confirmation' => 'qisqa'])->assertSessionHasErrors('password');
        $this->post(route('admin.users.store'), [...$base, 'username' => 'yaxshi', 'password_confirmation' => 'boshqa'])->assertSessionHasErrors('password');
    }

    public function test_admin_cannot_demote_or_block_themselves(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin);

        $this->put(route('admin.users.update', $admin), ['name' => $admin->name, 'username' => $admin->username, 'role' => 'viewer', 'is_active' => '1'])
            ->assertSessionHasErrors('role');
        $this->put(route('admin.users.update', $admin), ['name' => $admin->name, 'username' => $admin->username, 'role' => 'admin', 'is_active' => '0'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_can_change_own_password_and_stays_logged_in(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin);

        $this->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'username' => $admin->username, 'role' => 'admin', 'is_active' => '1',
            'password' => self::STRONG_PASSWORD, 'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $admin->fresh()->password));
        $this->assertAuthenticatedAs($admin);
    }
}
