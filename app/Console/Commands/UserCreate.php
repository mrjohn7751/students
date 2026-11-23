<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Yangi foydalanuvchi qo'shish. Ochiq ro'yxatdan o'tish yo'q — hisoblar faqat shu buyruq orqali yaratiladi.
 */
class UserCreate extends Command
{
    protected $signature = 'user:create
        {email : Foydalanuvchi emaili (login)}
        {--name= : F.I.Sh.}
        {--role=staff : admin | staff | viewer}
        {--generate-password : Kuchli parolni avtomatik yaratish va bir marta ko\'rsatish}';

    protected $description = 'UniRegistry\'ga yangi foydalanuvchi qo\'shish';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $name = $this->option('name') ?: $this->ask('F.I.Sh.');
        $generated = (bool) $this->option('generate-password');
        $password = $generated ? Str::password(20) : $this->secret('Parol (kamida 12 belgi, katta-kichik harf va raqam)');
        if (! $generated && $password !== $this->secret('Parolni takrorlang')) {
            $this->error('Parollar mos kelmadi.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'role' => $this->option('role'), 'password' => $password],
            [
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'name' => ['required', 'string', 'max:255'],
                'role' => ['required', Rule::enum(UserRole::class)],
                'password' => ['required', Password::defaults()],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::from($this->option('role')),
            'is_active' => true,
        ]);
        AuditLog::record('user.created', $user->email, ['role' => $user->role->value, 'via' => 'cli']);

        $this->info("Foydalanuvchi yaratildi: {$user->email} ({$user->role->label()})");
        if ($generated) {
            $this->warn("Parol (faqat hozir ko'rsatiladi): {$password}");
        }

        return self::SUCCESS;
    }
}
