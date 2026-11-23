@php
    $isNew = ! $user->exists;
    $isSelf = ! $isNew && $user->is(auth()->user());
    $error = fn (string $field) => $errors->first($field);
@endphp

<x-layout :title="$isNew ? 'Yangi foydalanuvchi' : $user->name">
    <x-slot:actions>
        <a href="{{ route('admin.users.index') }}" class="nav-btn">← Foydalanuvchilar</a>
    </x-slot:actions>

    <section class="card p-6 max-w-2xl">
        <h1 class="text-xl font-extrabold tracking-tight">{{ $isNew ? 'Yangi foydalanuvchi' : 'Foydalanuvchini tahrirlash' }}</h1>
        <p class="text-sm secondary mt-1">
            {{ $isNew ? 'Login va boshlang‘ich parolni xodimga shaxsan yetkazing.' : 'Parolni o‘zgartirmaslik uchun parol maydonlarini bo‘sh qoldiring.' }}
        </p>

        @if ($errors->any())
            <div role="alert" class="mt-4 rounded-xl px-3 py-2.5 text-sm font-semibold"
                 style="background: color-mix(in srgb, var(--critical) 14%, transparent); color: var(--critical)">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ $isNew ? route('admin.users.store') : route('admin.users.update', $user) }}" class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4" autocomplete="off">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <label class="flex flex-col gap-1.5 text-sm font-semibold sm:col-span-2">
                F.I.Sh.
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="field !bg-none !pr-3 font-normal">
                @if ($error('name'))<span class="text-xs font-normal" style="color: var(--critical)">{{ $error('name') }}</span>@endif
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-semibold">
                Login
                <input type="text" name="username" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="50"
                       pattern="[a-z0-9._\-]+" autocapitalize="none" spellcheck="false" autocomplete="off" class="field !bg-none !pr-3 font-normal">
                <span class="text-xs font-normal muted">Kichik lotin harflari, raqam, nuqta, «-» va «_»</span>
                @if ($error('username'))<span class="text-xs font-normal" style="color: var(--critical)">{{ $error('username') }}</span>@endif
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-semibold">
                Rol
                <select name="role" class="field font-normal" @disabled($isSelf)>
                    @foreach (\App\Enums\UserRole::cases() as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                @if ($isSelf)
                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                    <span class="text-xs font-normal muted">O‘z rolingizni o‘zgartira olmaysiz</span>
                @else
                    <span class="text-xs font-normal muted">Xodim — talabalar va eksport; Kuzatuvchi — faqat statistika</span>
                @endif
                @if ($error('role'))<span class="text-xs font-normal" style="color: var(--critical)">{{ $error('role') }}</span>@endif
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-semibold">
                {{ $isNew ? 'Parol' : 'Yangi parol' }}
                <input type="password" name="password" @if ($isNew) required @endif autocomplete="new-password" maxlength="255" class="field !bg-none !pr-3 font-normal">
                <span class="text-xs font-normal muted">Kamida 12 belgi: katta va kichik harf, raqam</span>
                @if ($error('password'))<span class="text-xs font-normal" style="color: var(--critical)">{{ $error('password') }}</span>@endif
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-semibold">
                Parolni takrorlang
                <input type="password" name="password_confirmation" @if ($isNew) required @endif autocomplete="new-password" maxlength="255" class="field !bg-none !pr-3 font-normal">
            </label>

            @unless ($isNew)
                <label class="flex items-center gap-2 text-sm font-semibold sm:col-span-2">
                    <input type="hidden" name="is_active" value="{{ $isSelf ? 1 : 0 }}">
                    <input type="checkbox" name="is_active" value="1" class="size-4" @checked(old('is_active', $user->is_active)) @disabled($isSelf)>
                    Faol (belgi olib tashlansa — hisob bloklanadi va barcha sessiyalari yopiladi)
                </label>
            @endunless

            <div class="sm:col-span-2 flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-bold text-white" style="background: #1c5cab">
                    {{ $isNew ? 'Qo‘shish' : 'Saqlash' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn-ghost">Bekor qilish</a>
            </div>
        </form>
    </section>
</x-layout>
