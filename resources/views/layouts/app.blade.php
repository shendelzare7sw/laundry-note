@php
    $judul = $judul ?? config('app.name', 'Laundry-Kos');
    $penggunaSaatIni = auth()->user();
    $namaAplikasi = config('app.name', 'Laundry-Kos');
    $namaProperti = $propertiAktif?->nama;
    $subjudulHeader = $subjudul ?? $namaProperti ?? ($penggunaSaatIni->role->isGlobal() ? 'Pilih properti untuk dikelola' : $namaAplikasi);
    $sudahPilihProperti = $propertiAktif !== null;
    $jumlahNotifikasi = isset($daftarNotifikasi) ? $daftarNotifikasi->count() : 0;
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
            x-bind:class="{ 'translate-x-0': drawerOpen, '-translate-x-full': !drawerOpen }"
            class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-maroon-dark text-white transition-transform duration-200 ease-out print:hidden lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-68 lg:shrink-0 lg:translate-x-0"
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
                        <h1 class="truncate text-sm font-bold text-ink sm:text-base">{{ $judul }}</h1>
                        <p class="truncate text-xs text-muted">{{ $subjudulHeader }}</p>
                    </div>

                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        @isset($aksiHeader)
                            <div class="flex shrink-0 items-center gap-2">
                                {{ $aksiHeader }}
                            </div>
                        @endisset

                        {{-- Lonceng Notifikasi --}}
                        <div class="relative" x-data="dropdownMenu" x-on:keydown.escape.window="close">
                            <button
                                type="button"
                                x-on:click="toggle"
                                :aria-expanded="open"
                                aria-label="Notifikasi"
                                class="relative flex h-11 w-11 items-center justify-center rounded-xl border border-line-strong bg-surface text-ink hover:bg-sunken"
                            >
                                <i class="fas fa-bell text-base {{ $jumlahNotifikasi > 0 ? 'text-maroon' : 'text-muted' }}" aria-hidden="true"></i>
                                @if ($jumlahNotifikasi > 0)
                                    <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white shadow-xs">
                                        {{ $jumlahNotifikasi > 99 ? '99+' : $jumlahNotifikasi }}
                                    </span>
                                @endif
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                x-on:click.outside="close"
                                class="absolute right-0 top-full z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-line bg-surface p-2 shadow-xl"
                            >
                                <div class="flex items-center justify-between border-b border-line px-3 py-2">
                                    <span class="text-sm font-bold text-ink">Notifikasi</span>
                                    <span class="rounded-full bg-sunken px-2 py-0.5 text-[11px] font-bold text-muted">
                                        {{ $jumlahNotifikasi }} item
                                    </span>
                                </div>

                                @if ($jumlahNotifikasi === 0)
                                    <div class="px-3 py-6 text-center text-xs text-muted">
                                        Belum ada peringatan kuota atau notifikasi baru saat ini.
                                    </div>
                                @else
                                    <ul class="max-h-72 space-y-1 overflow-y-auto py-1">
                                        @foreach ($daftarNotifikasi->take(8) as $notif)
                                            @php
                                                $ikonNotif = match ($notif['tipe']) {
                                                    'danger' => 'fa-triangle-exclamation text-danger bg-danger/10',
                                                    'warn' => 'fa-circle-exclamation text-warn bg-gold-container',
                                                    default => 'fa-sliders text-info bg-info/10',
                                                };
                                            @endphp
                                            <li>
                                                <a
                                                    href="{{ $notif['url'] }}"
                                                    class="flex items-start gap-2.5 rounded-xl px-2.5 py-2 hover:bg-sunken"
                                                >
                                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $ikonNotif }}">
                                                        <i class="fas {{ explode(' ', $ikonNotif)[0] }} text-xs" aria-hidden="true"></i>
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-xs font-bold text-ink">{{ $notif['judul'] }}</p>
                                                        <p class="mt-0.5 text-[11px] leading-snug text-muted">{{ $notif['keterangan'] }}</p>
                                                    </div>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if ($sudahPilihProperti)
                                        <div class="border-t border-line pt-1.5">
                                            <a
                                                href="{{ route('penghuni.index') }}"
                                                class="flex min-h-10 items-center justify-center rounded-xl text-xs font-bold text-maroon hover:bg-sunken"
                                            >
                                                Lihat Semua Penghuni →
                                            </a>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>

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

                                <form
                                    method="POST"
                                    action="{{ route('logout') }}"
                                    data-confirm-title="Keluar dari Laundry-Kos?"
                                    data-confirm-message="Sesi login Anda akan diakhiri dan Anda perlu masuk kembali untuk mengakses aplikasi."
                                    data-confirm-label="Ya, Keluar"
                                    data-confirm-variant="danger"
                                    class="mt-1 border-t border-line pt-1"
                                >
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
    <x-dialog-konfirmasi />
</body>
</html>
