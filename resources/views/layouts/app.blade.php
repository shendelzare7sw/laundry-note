@php
    $judul = $judul ?? config('app.name', 'Laundry-Kos');
    $penggunaSaatIni = auth()->user();
    $namaAplikasi = config('app.name', 'Laundry-Kos');
    $namaProperti = $propertiAktif?->nama;
    $subjudulHeader = $namaProperti ?? ($penggunaSaatIni->role->isGlobal() ? 'Pilih properti untuk dikelola' : $namaAplikasi);
    $sudahPilihProperti = $propertiAktif !== null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <title>{{ $judul }} — {{ $namaProperti ?? $namaAplikasi }}</title>
    @include('layouts.partials.head-assets')
</head>
<body class="min-h-screen bg-bg font-sans text-ink antialiased">
    <div x-data="appShell" x-on:keydown.escape.window="closeDrawer" class="min-h-screen lg:flex">
        {{-- Backdrop Drawer Mobile --}}
        <div
            x-show="drawerOpen"
            x-cloak
            x-on:click="closeDrawer"
            class="fixed inset-0 z-40 bg-ink/50 backdrop-blur-[1px] print:hidden lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Sidebar Navigasi (Drawer di Mobile, Tetap di Desktop) --}}
        <aside
            :class="drawerOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-maroon-dark text-white transition-transform duration-200 ease-out print:hidden lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-68 lg:shrink-0"
            aria-label="Navigasi samping"
        >
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-4">
                <img
                    src="{{ asset('img/favicon-tab-browser.jpg') }}"
                    alt="{{ $namaAplikasi }}"
                    class="h-10 w-10 shrink-0 rounded-xl bg-white object-contain p-1"
                >
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-bold uppercase tracking-wider text-gold-soft">{{ $namaAplikasi }}</div>
                    <div class="truncate text-sm font-bold text-white">{{ $namaProperti ?? 'Panel Manajemen' }}</div>
                </div>
                <button
                    type="button"
                    x-on:click="closeDrawer"
                    aria-label="Tutup menu"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
                >
                    <i class="fas fa-xmark text-base" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-4">
                @if ($sudahPilihProperti || $penggunaSaatIni->role->isStafProperti())
                    <div>
                        <div class="mb-1.5 px-3 text-[10px] font-bold uppercase tracking-widest text-white/45">
                            Operasional
                        </div>
                        <ul class="space-y-1">
                            <li>
                                <a
                                    href="{{ route('beranda') }}"
                                    @if (request()->routeIs('beranda')) aria-current="page" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors {{ request()->routeIs('beranda') ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"
                                >
                                    <i class="fas fa-house w-5 text-center {{ request()->routeIs('beranda') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                    <span>Beranda</span>
                                </a>
                            </li>
                            <li>
                                <a
                                    href="{{ route('laundry.index') }}"
                                    @if (request()->routeIs('laundry.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors {{ request()->routeIs('laundry.*') ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"
                                >
                                    <i class="fas fa-shirt w-5 text-center {{ request()->routeIs('laundry.*') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                    <span>Laundry</span>
                                </a>
                            </li>
                            <li>
                                <a
                                    href="{{ route('penghuni.index') }}"
                                    @if (request()->routeIs('penghuni.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors {{ request()->routeIs('penghuni.*') ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"
                                >
                                    <i class="fas fa-users w-5 text-center {{ request()->routeIs('penghuni.*') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                    <span>Penghuni</span>
                                </a>
                            </li>
                            <li>
                                <a
                                    href="{{ route('laporan.index') }}"
                                    @if (request()->routeIs('laporan.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors {{ request()->routeIs('laporan.*') ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"
                                >
                                    <i class="fas fa-file-lines w-5 text-center {{ request()->routeIs('laporan.*') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                    <span>Laporan</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif

                <div>
                    <div class="mb-1.5 px-3 text-[10px] font-bold uppercase tracking-widest text-white/45">
                        Manajemen
                    </div>
                    <ul class="space-y-1">
                        @can('viewAny', \App\Models\Properti::class)
                            <li>
                                <a
                                    href="{{ route('properti.index') }}"
                                    @if (request()->routeIs('properti.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors {{ request()->routeIs('properti.*') ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"
                                >
                                    <i class="fas fa-building w-5 text-center {{ request()->routeIs('properti.*') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                    <span>{{ $sudahPilihProperti ? 'Kelola / Ganti Properti' : 'Pilih Properti' }}</span>
                                </a>
                            </li>
                        @endcan

                        @can('viewAny', \App\Models\User::class)
                            <li>
                                <a
                                    href="{{ route('pengguna.index') }}"
                                    @if (request()->routeIs('pengguna.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors {{ request()->routeIs('pengguna.*') ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"
                                >
                                    <i class="fas fa-user-gear w-5 text-center {{ request()->routeIs('pengguna.*') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                    <span>Kelola Pengguna</span>
                                </a>
                            </li>
                        @endcan

                        <li>
                            <a
                                href="{{ route('lainnya.index') }}"
                                @if (request()->routeIs('lainnya.*')) aria-current="page" @endif
                                class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors {{ request()->routeIs('lainnya.*') ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"
                            >
                                <i class="fas fa-sliders w-5 text-center {{ request()->routeIs('lainnya.*') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                <span>Lainnya</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-2xl bg-white/10 p-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gold-soft text-sm font-bold text-maroon-darker">
                        {{ strtoupper(mb_substr($penggunaSaatIni->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-bold text-white">{{ $penggunaSaatIni->name }}</div>
                        <div class="truncate text-[11px] text-white/65">{{ $penggunaSaatIni->role->label() }}</div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Area Kanan (Topbar + Konten Penuh) --}}
        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-line bg-surface print:hidden">
                <div class="flex h-16 min-w-0 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        x-on:click="toggleDrawer"
                        aria-label="Buka menu navigasi"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-line-strong bg-surface text-ink hover:bg-sunken lg:hidden"
                    >
                        <i class="fas fa-bars text-base" aria-hidden="true"></i>
                    </button>

                    <span class="hidden h-9 w-1.5 shrink-0 rounded-full bg-maroon sm:block" aria-hidden="true"></span>

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-bold text-ink sm:text-base">{{ $judul }}</div>
                        <div class="truncate text-xs text-muted">{{ $subjudulHeader }}</div>
                    </div>

                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        @if ($penggunaSaatIni->role->isGlobal() && $sudahPilihProperti)
                            <form method="POST" action="{{ route('properti.lepas') }}" class="hidden sm:block">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line-strong bg-alt px-3 text-xs font-bold text-ink hover:bg-sunken"
                                >
                                    <i class="fas fa-arrow-right-arrow-left text-maroon" aria-hidden="true"></i>
                                    <span>Ganti Properti</span>
                                </button>
                            </form>
                        @endif

                        {{-- Dropdown Pengguna --}}
                        <div class="relative" x-data="dropdownMenu" x-on:keydown.escape.window="close">
                            <button
                                type="button"
                                x-on:click="toggle"
                                :aria-expanded="open"
                                aria-label="Menu akun"
                                class="flex h-11 items-center gap-2 rounded-xl border border-line-strong bg-surface px-2.5 hover:bg-sunken"
                            >
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-maroon text-xs font-bold text-white">
                                    {{ strtoupper(mb_substr($penggunaSaatIni->name, 0, 1)) }}
                                </span>
                                <span class="hidden text-left md:block">
                                    <span class="block max-w-32 truncate text-xs font-bold text-ink">{{ $penggunaSaatIni->name }}</span>
                                    <span class="block text-[10px] text-muted">{{ $penggunaSaatIni->role->label() }}</span>
                                </span>
                                <i class="fas fa-chevron-down hidden text-[10px] text-muted md:block" aria-hidden="true"></i>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                x-on:click.outside="close"
                                class="absolute right-0 top-full z-50 mt-2 w-60 rounded-2xl border border-line bg-surface p-2 shadow-xl"
                            >
                                <div class="border-b border-line px-3 py-2">
                                    <div class="truncate text-sm font-bold text-ink">{{ $penggunaSaatIni->name }}</div>
                                    <div class="truncate text-xs text-muted">{{ '@' . $penggunaSaatIni->username }} · {{ $penggunaSaatIni->role->label() }}</div>
                                </div>

                                @if ($penggunaSaatIni->role->isGlobal() && $sudahPilihProperti)
                                    <form method="POST" action="{{ route('properti.lepas') }}" class="mt-1 sm:hidden">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="flex min-h-11 w-full items-center gap-2.5 rounded-xl px-3 text-sm font-medium text-ink hover:bg-sunken"
                                        >
                                            <i class="fas fa-arrow-right-arrow-left w-4 text-maroon" aria-hidden="true"></i>
                                            <span>Ganti Properti</span>
                                        </button>
                                    </form>
                                @endif

                                <a
                                    href="{{ route('lainnya.index') }}"
                                    class="mt-1 flex min-h-11 items-center gap-2.5 rounded-xl px-3 text-sm font-medium text-ink hover:bg-sunken"
                                >
                                    <i class="fas fa-sliders w-4 text-muted" aria-hidden="true"></i>
                                    <span>Menu Lainnya</span>
                                </a>

                                <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-line pt-1">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="flex min-h-11 w-full items-center gap-2.5 rounded-xl px-3 text-sm font-bold text-danger hover:bg-danger/10"
                                    >
                                        <i class="fas fa-right-from-bracket w-4" aria-hidden="true"></i>
                                        <span>Keluar</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Konten Penuh Tanpa max-w Pembatas --}}
            <main class="w-full min-w-0 flex-1 px-4 pt-4 pb-24 sm:px-6 sm:pt-5 lg:px-8 lg:pb-8">
                <div class="w-full min-w-0 space-y-5">
                    {{ $slot }}
                </div>
            </main>
        </div>

        <x-bottom-nav />
    </div>

    <x-toast />
</body>
</html>
