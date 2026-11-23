<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>UniRegistry — Kirish</title>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased min-h-screen grid place-items-center px-4 py-10">
<main class="w-full max-w-[400px]">
    <div class="flex items-center gap-3 mb-6 justify-center">
        <div class="size-12 rounded-2xl grid place-items-center font-extrabold text-white" style="background: linear-gradient(135deg, #1c5cab, #2a78d6)">UR</div>
        <div>
            <div class="text-xl font-extrabold tracking-tight">UniRegistry</div>
            <p class="text-xs muted">Jizzax davlat pedagogika universiteti</p>
        </div>
    </div>

    <section class="card p-6 sm:p-7">
        <h1 class="text-lg font-extrabold tracking-tight">Tizimga kirish</h1>
        <p class="text-sm secondary mt-1">Talabalar reyestri faqat vakolatli xodimlar uchun.</p>

        @if ($errors->any())
            <div role="alert" class="mt-4 rounded-xl px-3 py-2.5 text-sm font-semibold"
                 style="background: color-mix(in srgb, var(--critical) 14%, transparent); color: var(--critical)">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-5 flex flex-col gap-4" autocomplete="on">
            @csrf
            <label class="flex flex-col gap-1.5 text-sm font-semibold">
                Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       maxlength="255" class="field !bg-none !pr-3 font-normal">
            </label>
            <label class="flex flex-col gap-1.5 text-sm font-semibold">
                Parol
                <input type="password" name="password" required autocomplete="current-password"
                       maxlength="255" class="field !bg-none !pr-3 font-normal">
            </label>
            <button type="submit" class="mt-1 rounded-xl px-4 py-2.5 text-sm font-bold text-white" style="background: #1c5cab">
                Kirish
            </button>
        </form>
    </section>

    <p class="text-xs muted text-center mt-5">Hisob olish uchun Ma’lumotlar bazasi va xizmat ko‘rsatish bo‘limiga murojaat qiling.<br>Barcha kirishlar va ko‘rilgan ma’lumotlar jurnalga yoziladi.</p>
</main>
</body>
</html>
