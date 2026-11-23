<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kirish/chiqish. Ochiq ro'yxatdan o'tish yo'q — foydalanuvchilarni administrator qo'shadi (`php artisan user:create`).
 */
class LoginController extends Controller
{
    /** Bitta hisob + IP uchun ketma-ket xato urinishlar chegarasi va bloklash vaqti (soniya). */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $email = Str::lower(trim($credentials['email']));
        $throttleKey = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            AuditLog::record('auth.lockout', $email);
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'email' => "Juda ko‘p noto‘g‘ri urinish. {$minutes} daqiqadan keyin qayta urinib ko‘ring.",
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password'], 'is_active' => true])) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);
            AuditLog::record('auth.failed', $email, ['user_id' => User::query()->where('email', $email)->value('id')]);

            // Hisob mavjudligi yoki bloklanganini oshkor qilmaslik uchun bitta umumiy xabar
            throw ValidationException::withMessages(['email' => 'Email yoki parol noto‘g‘ri.'])->redirectTo(route('login'));
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('auth.login', $user->email);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record('auth.logout', $request->user()?->email);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
