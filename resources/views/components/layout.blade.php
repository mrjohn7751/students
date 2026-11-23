@props(['title' => 'Statistika'])

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UniRegistry — {{ $title }}</title>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased min-h-screen">
<div class="max-w-[1480px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <header class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="flex items-center gap-3">
            <div class="size-11 rounded-2xl grid place-items-center font-extrabold text-white" style="background: linear-gradient(135deg, #1c5cab, #2a78d6)">UR</div>
            <div>
                <div class="text-xl font-extrabold tracking-tight">UniRegistry</div>
                <p class="text-xs muted">Talabalar yagona tarixiy reyestri · Jizzax davlat pedagogika universiteti</p>
            </div>
        </a>
        <div class="flex items-center gap-2">
            {{ $actions ?? '' }}
            @auth
                @can('view-personal-data')
                    @unless (request()->routeIs('students.*'))
                        <a href="{{ route('students.index') }}" class="chip hover:opacity-80">Talabalar</a>
                    @endunless
                @endcan
            @endauth
            <button type="button" id="theme-toggle" class="btn-ghost card !rounded-xl px-3 py-2" aria-label="Mavzuni almashtirish">◐ Mavzu</button>
            @auth
                <span class="hidden sm:inline-flex flex-col items-end leading-tight text-xs pl-2">
                    <span class="font-semibold">{{ auth()->user()->name }}</span>
                    <span class="muted">{{ auth()->user()->role->label() }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost card !rounded-xl px-3 py-2">Chiqish</button>
                </form>
            @endauth
        </div>
    </header>

    {{ $slot }}

    <footer class="text-xs muted text-center mt-10">UniRegistry · Ma’lumotlar bazasi va xizmat ko‘rsatish bo‘limi</footer>
</div>
</body>
</html>
