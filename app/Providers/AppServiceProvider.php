<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-personal-data', fn (User $user) => $user->is_active && $user->role->canViewPersonalData());
        Gate::define('manage-users', fn (User $user) => $user->is_active && $user->role === UserRole::Admin);
        Gate::define('export-data', fn (User $user) => $user->is_active && $user->role->canExport());

        // Shaxsiy ma'lumotlarni skript bilan ommaviy yig'ib olishdan himoya
        RateLimiter::for('personal-data', fn (Request $request) => Limit::perMinute(90)->by('pd:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('exports', fn (Request $request) => [
            Limit::perMinute(3)->by('ex-m:'.($request->user()?->id ?? $request->ip())),
            Limit::perDay(30)->by('ex-d:'.($request->user()?->id ?? $request->ip())),
        ]);
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by('login-ip:'.$request->ip()));

        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers()->uncompromised());
    }
}
