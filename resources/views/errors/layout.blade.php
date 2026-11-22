<!DOCTYPE html>
<html lang="id">
<head>
    <title>@yield('kode') — @yield('judul') | {{ config('app.name', 'Laundry-Kos') }}</title>
    @include('layouts.partials.head-assets')
</head>
<body class="flex min-h-screen items-center justify-center bg-bg px-4 py-10 font-sans text-ink antialiased">
    @php
        $sudahLogin = auth()->check();
        $tujuanUtama = $sudahLogin ? route('beranda') : route('login');
        $labelUtama = $sudahLogin ? 'Kembali ke Beranda' : 'Masuk ke Aplikasi';
    @endphp

    <main class="w-full max-w-md">
        <section class="rounded-3xl border border-line bg-surface p-6 text-center shadow-sm sm:p-8">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-maroon/10 text-maroon">
                <i class="fas @yield('ikon', 'fa-circle-exclamation') text-2xl" aria-hidden="true"></i>
            </div>

            <p class="mt-4 text-xs font-bold uppercase tracking-widest text-gold">
                Kode @yield('kode')
            </p>

            <h1 class="mt-1 text-xl font-bold text-ink sm:text-2xl">
                @yield('judul')
            </h1>

            <p class="mt-2 text-sm leading-relaxed text-muted">
                @yield('pesan')
            </p>

            <div class="mt-6 flex flex-col gap-2.5 sm:flex-row sm:justify-center">
                <a
                    href="{{ $tujuanUtama }}"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah"
                >
                    <i class="fas {{ $sudahLogin ? 'fa-house' : 'fa-right-to-bracket' }} text-xs" aria-hidden="true"></i>
                    <span>{{ $labelUtama }}</span>
                </a>

                @if ($sudahLogin)
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken"
                    >
                        <i class="fas fa-arrow-rotate-right text-xs" aria-hidden="true"></i>
                        <span>Halaman Login</span>
                    </a>
                @endif
            </div>
        </section>

        <p class="mt-4 text-center text-xs text-subtle">
            {{ config('app.name', 'Laundry-Kos') }}
        </p>
    </main>
</body>
</html>
