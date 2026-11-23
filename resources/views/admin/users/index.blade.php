<x-layout title="Foydalanuvchilar">
    <x-slot:actions>
        <a href="{{ route('dashboard') }}" class="nav-btn">← Dashboard</a>
    </x-slot:actions>

    <section class="card p-5 mb-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight">Foydalanuvchilar</h1>
            <p class="text-sm secondary mt-1">Tizimga kirish huquqiga ega xodimlar. Login, parol, rol va holatni shu yerdan o‘zgartiring.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white" style="background: #1c5cab">
            + Yangi foydalanuvchi
        </a>
    </section>

    @if (session('status'))
        <div role="status" class="card px-5 py-3 mb-4 text-sm font-semibold" style="border-color: var(--good); color: var(--good)">{{ session('status') }}</div>
    @endif

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table min-w-[820px]">
                <thead><tr>
                    <th>F.I.Sh.</th>
                    <th>Login</th>
                    <th>Rol</th>
                    <th>Holat</th>
                    <th>Oxirgi kirish</th>
                    <th class="w-32"></th>
                </tr></thead>
                <tbody>
                @foreach ($users as $user)
                    <tr class="hover:bg-[var(--surface-2)] {{ $user->is_active ? '' : 'opacity-60' }}">
                        <td class="font-semibold !text-[var(--text-primary)]">
                            {{ $user->name }}
                            @if ($user->is(auth()->user()))<span class="chip ml-1">Siz</span>@endif
                        </td>
                        <td class="tabular-nums">{{ $user->username }}</td>
                        <td>{{ $user->role->label() }}</td>
                        <td>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="swatch rounded-full" style="background: var({{ $user->is_active ? '--good' : '--critical' }})"></span>
                                {{ $user->is_active ? 'Faol' : 'Bloklangan' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap">{{ $user->last_login_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.users.edit', $user) }}" class="font-semibold hover:underline" style="color: var(--accent)">Tahrirlash</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
