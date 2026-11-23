<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin paneli: foydalanuvchilarni qo'shish, login/parol/rol/holatini o'zgartirish.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['role' => UserRole::Staff, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => User::usernameRules(),
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([...$data, 'is_active' => true]);
        AuditLog::record('user.created', $user->username, ['role' => $user->role->value, 'via' => 'web']);

        return redirect()->route('admin.users.index')->with('status', "«{$user->username}» foydalanuvchisi qo‘shildi.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => User::usernameRules($user->id),
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $isSelf = $request->user()->is($user);
        if ($isSelf && ($data['role'] !== UserRole::Admin->value || ! $data['is_active'])) {
            // Tizim administratorsiz qolib ketmasligi uchun
            throw ValidationException::withMessages(['role' => 'O‘zingizning admin huquqingizni olib tashlay yoki hisobingizni bloklay olmaysiz.']);
        }

        $passwordChanged = filled($data['password'] ?? null);
        if (! $passwordChanged) {
            unset($data['password']);
        }

        $user->fill($data);
        $changed = array_keys($user->getDirty());
        $user->save();

        // Parol o'zgarsa yoki hisob bloklansa — boshqa qurilmalardagi sessiyalar yopiladi
        if ($passwordChanged || ! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)
                ->when($isSelf, fn ($q) => $q->where('id', '!=', $request->session()->getId()))
                ->delete();
        }

        AuditLog::record('user.updated', $user->username, [
            'changed' => array_values(array_diff($changed, ['password'])) ?: null,
            'password_changed' => $passwordChanged,
            'via' => 'web',
        ]);

        return redirect()->route('admin.users.index')->with('status', "«{$user->username}» ma’lumotlari saqlandi.".($passwordChanged ? ' Parol yangilandi.' : ''));
    }
}
