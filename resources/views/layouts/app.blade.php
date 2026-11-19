@php
    $judul = $judul ?? config('app.name', 'Laundry-Kos');
    $penggunaSaatIni = auth()->user();
    $namaProperti = $propertiAktif?->nama ?? config('app.name', 'Laundry-Kos');
    $menuSamping = [
        ['route' => 'beranda', 'label' => 'Beranda', 'aktif' => request()->routeIs('beranda')],
        ['route' => 'laundry.index', 'label' => 'Laundry', 'aktif' => request()->routeIs('laundry.*')],
        ['route' => 'penghuni.index', 'label' => 'Penghuni', 'aktif' => request()->routeIs('penghuni.*')],
        ['route' => 'laporan.index', 'label' => 'Laporan', 'aktif' => request()->routeIs('laporan.*')],
        ['route' => 'lainnya.index', 'label' => 'Lainnya', 'aktif' => request()->routeIs('lainnya.*')],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul }} — {{ $namaProperti }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32x32.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-bg font-sans text-ink antialiased">
    <div class="flex min-h-screen flex-col lg:flex-row">
        {{-- Sidebar Desktop --}}
        <aside class="hidden print:hidden lg:flex lg:w-64 lg:shrink-0 lg:flex-col lg:border-r lg:border-line lg:bg-surface">
            <div class="bg-maroon px-5 py-4 text-white">
                <div class="flex items-center gap-3">
                    <img
                        src="{{ asset('img/favicon-tab-browser.jpg') }}"
                        alt="{{ config('app.name', 'Laundry-Kos') }}"
                        class="h-10 w-10 shrink-0 rounded-xl bg-white object-contain p-1"
                    >
                    <div class="min-w-0">
                        <div class="text-xs font-medium uppercase tracking-wider text-gold-soft">{{ config('app.name', 'Laundry-Kos') }}</div>
                        <div class="mt-0.5 truncate text-base font-bold leading-snug">{{ $namaProperti }}</div>
                    </div>
                </div>
                <div class="mt-2.5 inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium">
                    {{ $penggunaSaatIni->name }} · {{ $penggunaSaatIni->role->label() }}
                </div>
            </div>

            @if ($penggunaSaatIni->role->isGlobal() && isset($daftarProperti) && $daftarProperti->isNotEmpty())
                <div class="border-b border-line bg-alt p-3">
                    <form method="POST" action="{{ route('properti.pilih', $propertiAktif ?? $daftarProperti->first()) }}" x-data>
                        @csrf
                        <label for="pemilih-properti-desktop" class="mb-1 block text-[11px] font-medium text-muted">
                            Properti aktif
                        </label>
                        <select
                            id="pemilih-properti-desktop"
                            class="min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink focus:border-maroon focus:outline-none"
                            x-on:change="
                                $el.form.action = '{{ url('/properti/pilih') }}/' + $el.value;
                                $el.form.submit();
                            "
                        >
                            @foreach ($daftarProperti as $item)
                                <option value="{{ $item->id }}" @selected($propertiAktif?->id === $item->id)>
                                    {{ $item->nama }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            @endif

            <nav aria-label="Menu utama" class="flex-1 space-y-1 p-3">
                @foreach ($menuSamping as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        @if ($item['aktif']) aria-current="page" @endif
                        class="flex min-h-11 items-center rounded-xl px-3.5 py-2 text-sm font-medium transition-colors {{ $item['aktif'] ? 'bg-maroon text-white font-bold' : 'text-ink hover:bg-sunken' }}"
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach

                @can('viewAny', \App\Models\User::class)
                    <a
                        href="{{ route('pengguna.index') }}"
                        @if (request()->routeIs('pengguna.*')) aria-current="page" @endif
                        class="flex min-h-11 items-center rounded-xl px-3.5 py-2 text-sm font-medium transition-colors {{ request()->routeIs('pengguna.*') ? 'bg-maroon text-white font-bold' : 'text-ink hover:bg-sunken' }}"
                    >
                        Kelola Pengguna
                    </a>
                @endcan

                @can('viewAny', \App\Models\Properti::class)
                    <a
                        href="{{ route('properti.index') }}"
                        @if (request()->routeIs('properti.*')) aria-current="page" @endif
                        class="flex min-h-11 items-center rounded-xl px-3.5 py-2 text-sm font-medium transition-colors {{ request()->routeIs('properti.*') ? 'bg-maroon text-white font-bold' : 'text-ink hover:bg-sunken' }}"
                    >
                        Kelola Properti
                    </a>
                @endcan
            </nav>

            <div class="border-t border-line p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex min-h-11 w-full items-center justify-center rounded-xl border border-line-strong bg-surface px-4 text-sm font-medium text-ink transition-colors hover:bg-sunken"
                    >
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        {{-- Area Konten Utama --}}
        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Header Mobile --}}
            <header class="sticky top-0 z-30 bg-maroon px-4 py-3 text-white shadow-xs print:hidden lg:hidden">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <img
                            src="{{ asset('img/favicon-tab-browser.jpg') }}"
                            alt="{{ config('app.name', 'Laundry-Kos') }}"
                            class="h-9 w-9 shrink-0 rounded-xl bg-white object-contain p-0.5"
                        >
                        <div class="min-w-0">
                            <div class="truncate text-base font-bold">{{ $namaProperti }}</div>
                            <div class="truncate text-xs text-white/85">
                                {{ $penggunaSaatIni->name }} · {{ $penggunaSaatIni->role->label() }}
                            </div>
                        </div>
                    </div>

                    @if ($penggunaSaatIni->role->isGlobal() && isset($daftarProperti) && $daftarProperti->count() > 1)
                        <form method="POST" action="{{ route('properti.pilih', $propertiAktif ?? $daftarProperti->first()) }}" x-data class="shrink-0">
                            @csrf
                            <label for="pemilih-properti-mobile" class="sr-only">Pilih properti aktif</label>
                            <select
                                id="pemilih-properti-mobile"
                                class="min-h-11 rounded-xl border border-white/25 bg-maroon-cerah px-2.5 text-xs font-medium text-white focus:outline-none"
                                x-on:change="
                                    $el.form.action = '{{ url('/properti/pilih') }}/' + $el.value;
                                    $el.form.submit();
                                "
                            >
                                @foreach ($daftarProperti as $item)
                                    <option value="{{ $item->id }}" @selected($propertiAktif?->id === $item->id) class="text-ink">
                                        {{ $item->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>
            </header>

            <main class="mx-auto w-full max-w-5xl flex-1 px-4 pt-4 pb-24 sm:px-6 lg:py-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-bottom-nav />
    <x-toast />
</body>
</html>
