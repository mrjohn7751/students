@props(['title' => 'Statistika'])

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JDPU Kontingent — {{ $title }}</title>
    <link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/png">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased min-h-screen">
<div class="max-w-[1480px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <header class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="flex items-center gap-3">
            <img src="{{ asset('images/jdpu.png') }}" alt="JDPU" class="size-11 shrink-0 rounded-full bg-white p-px" width="44" height="44">
            <div>
                <div class="text-xl font-extrabold tracking-tight">JDPU Kontingent</div>
                <p class="text-xs muted">Talabalar yagona tarixiy reyestri · Jizzax davlat pedagogika universiteti</p>
            </div>
        </a>
        <nav class="flex flex-wrap items-center gap-2" aria-label="Asosiy menyu">
            {{ $actions ?? '' }}
            @auth
                @can('view-personal-data')
                    @unless (request()->routeIs('students.*'))
                        <a href="{{ route('students.index') }}" class="nav-btn">Talabalar</a>
                    @endunless
                @endcan
            @endauth
            <button type="button" id="theme-toggle" class="nav-btn" aria-label="Mavzuni almashtirish">◐ Mavzu</button>
            @auth
                @php
                    $currentUser = auth()->user();
                    $initials = collect(preg_split('/\s+/u', trim($currentUser->name)))->filter()->take(2)
                        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
                @endphp
                <details class="relative" data-dropdown>
                    <summary class="nav-btn !py-1.5 !pl-1.5 list-none cursor-pointer select-none" aria-label="Foydalanuvchi menyusi">
                        <span class="size-8 rounded-full grid place-items-center text-xs font-extrabold text-white shrink-0" style="background: #1c5cab">{{ $initials ?: '?' }}</span>
                        <span class="flex flex-col items-start leading-tight text-left">
                            <span class="font-bold">{{ $currentUser->name }}</span>
                            <span class="text-xs muted font-medium">{{ $currentUser->role->label() }}</span>
                        </span>
                        <svg class="dropdown-chevron size-4 ml-1 muted shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </summary>
                    <div class="card absolute right-0 mt-2 w-60 p-1.5 z-50 shadow-xl">
                        <div class="px-3 py-2 text-xs muted">Login: <b class="secondary">{{ $currentUser->username }}</b></div>
                        @can('manage-users')
                            <a href="{{ route('admin.users.index') }}" class="menu-item">Foydalanuvchilar</a>
                            <a href="{{ route('admin.users.edit', $currentUser) }}" class="menu-item">Parolni o‘zgartirish</a>
                        @endcan
                        <form method="POST" action="{{ route('logout') }}" class="border-t mt-1 pt-1" style="border-color: var(--border)">
                            @csrf
                            <button type="submit" class="menu-item w-full text-left" style="color: var(--critical)">Chiqish</button>
                        </form>
                    </div>
                </details>
            @endauth
        </nav>
    </header>

    {{ $slot }}

    <footer class="text-xs muted text-center mt-10">JDPU Kontingent · Ma’lumotlar bazasi va xizmat ko‘rsatish bo‘limi</footer>
</div>
</body>
</html>
