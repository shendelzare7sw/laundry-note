<!DOCTYPE html>
<html lang="id">
<head>
    <title>{{ $judul ?? 'Masuk' }} — {{ config('app.name', 'Laundry-Kos') }}</title>
    @include('layouts.partials.head-assets')
</head>
<body class="min-h-screen bg-bg font-sans text-ink antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Panel Identitas (Desktop) --}}
        <aside class="relative hidden overflow-hidden bg-maroon-dark p-10 text-white lg:flex lg:flex-col xl:p-14">
            <div class="flex items-center gap-3.5">
                <img
                    src="{{ asset('img/favicon-tab-browser.jpg') }}"
                    alt="{{ config('app.name', 'Laundry-Kos') }}"
                    width="48"
                    height="48"
                    decoding="async"
                    class="h-12 w-12 rounded-2xl bg-white object-contain p-1 shadow-xs"
                >
                <div>
                    <div class="text-lg font-bold tracking-tight">{{ config('app.name', 'Laundry-Kos') }}</div>
                    <div class="text-xs text-gold-soft">Sistem Internal Catatan Laundry &amp; Kamar</div>
                </div>
            </div>

            <div class="mt-auto max-w-lg space-y-5">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-gold-soft">
                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                    Akses Internal Terotorisasi
                </span>

                <h1 class="text-3xl font-bold leading-tight xl:text-4xl">
                    Kelola kuota laundry penghuni, penempatan kamar, dan laporan bulanan secara akurat.
                </h1>

                <p class="text-sm leading-relaxed text-white/75">
                    Perhitungan kuota terikat pada penghuni di setiap properti, mendukung perpindahan kamar tanpa mereset kuota, dan siap cetak laporan PDF maupun tagihan WhatsApp.
                </p>

                <div class="grid grid-cols-3 gap-3 pt-2">
                    <div class="rounded-2xl bg-white/10 p-3.5">
                        <i class="fas fa-scale-balanced text-lg text-gold-soft" aria-hidden="true"></i>
                        <div class="mt-2 text-xs font-bold">Kuota Akurat</div>
                        <div class="text-[11px] text-white/70">Per penghuni/bulan</div>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-3.5">
                        <i class="fas fa-building text-lg text-gold-soft" aria-hidden="true"></i>
                        <div class="mt-2 text-xs font-bold">Multi-Properti</div>
                        <div class="text-[11px] text-white/70">Terisolasi per gedung</div>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-3.5">
                        <i class="fas fa-file-invoice text-lg text-gold-soft" aria-hidden="true"></i>
                        <div class="mt-2 text-xs font-bold">Siap Cetak</div>
                        <div class="text-[11px] text-white/70">PDF &amp; WhatsApp</div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Area Form Login --}}
        <main class="flex flex-col items-center justify-center bg-bg px-4 py-8 sm:px-8 lg:bg-surface">
            <div class="w-full max-w-sm">
                <div class="mb-6 text-center">
                    <h1 class="sr-only">{{ config('app.name', 'Laundry-Kos') }}</h1>
                    <div class="mx-auto inline-flex items-center justify-center rounded-2xl border border-line bg-surface p-2 shadow-xs">
                        <img
                            src="{{ asset('img/logo-app.jpg') }}"
                            alt="{{ config('app.name', 'Laundry-Kos') }}"
                            width="112"
                            height="112"
                            fetchpriority="high"
                            class="h-24 w-24 rounded-xl object-contain sm:h-28 sm:w-28"
                        >
                    </div>
                    <p class="mt-3 text-sm text-muted">Masuk dengan akun yang telah didaftarkan admin</p>
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
