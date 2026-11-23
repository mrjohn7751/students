<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Mavjud foydalanuvchilarni boshqarish: ro'yxat, bloklash/ochish, rol va parolni almashtirish.
 */
class UserManage extends Command
{
    protected $signature = 'user:manage
        {action : list | block | unblock | role | password}
        {email? : Foydalanuvchi emaili}
        {--role= : role amali uchun: admin | staff | viewer}';

    protected $description = 'UniRegistry foydalanuvchilarini boshqarish';

    public function handle(): int
    {
        $action = $this->argument('action');
        if ($action === 'list') {
            $this->table(['ID', 'Email', 'F.I.Sh.', 'Rol', 'Faol', 'Oxirgi kirish'], User::query()->orderBy('id')->get()->map(fn (User $u) => [
                $u->id, $u->email, $u->name, $u->role->label(), $u->is_active ? 'ha' : 'BLOKLANGAN', $u->last_login_at?->format('d.m.Y H:i') ?? '—',
            ]));

            return self::SUCCESS;
        }

        $user = User::query()->where('email', Str::lower((string) $this->argument('email')))->first();
        if (! $user) {
            $this->error('Foydalanuvchi topilmadi.');

            return self::FAILURE;
        }

        switch ($action) {
            case 'block':
            case 'unblock':
                $user->forceFill(['is_active' => $action === 'unblock'])->save();
                if ($action === 'block') {
                    // Ochiq sessiyalarni ham yopamiz
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
                break;
            case 'role':
                $role = UserRole::tryFrom((string) $this->option('role'));
                if (! $role) {
                    $this->error('--role qiymati: admin, staff yoki viewer');

                    return self::FAILURE;
                }
                $user->forceFill(['role' => $role])->save();
                break;
            case 'password':
                $password = Str::password(20);
                validator(['password' => $password], ['password' => [Password::defaults()]])->validate();
                $user->forceFill(['password' => $password])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $this->warn("Yangi parol (faqat hozir ko'rsatiladi): {$password}");
                break;
            default:
                $this->error("Noma'lum amal: {$action}");

                return self::FAILURE;
        }

        AuditLog::record('user.'.$action, $user->email, ['role' => $user->role->value, 'via' => 'cli']);
        $this->info("Bajarildi: {$action} → {$user->email}");

        return self::SUCCESS;
    }
}
