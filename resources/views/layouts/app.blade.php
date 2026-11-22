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
        {{-- Backdrop Drawer Mobile (Tanpa backdrop-blur agar 60fps di Chrome mobile) --}}
        <div
            x-on:click="closeDrawer"
            x-on:wheel.prevent
            x-bind:class="{ 'opacity-100 pointer-events-auto': drawerOpen, 'opacity-0 pointer-events-none': !drawerOpen, 'is-animating': drawerAnimating }"
            class="drawer-backdrop fixed inset-0 z-40 touch-none overscroll-none bg-ink/45 opacity-0 pointer-events-none print:hidden lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Sidebar Navigasi (Drawer di Mobile, Tetap di Desktop) --}}
        <aside
            id="sidebar-nav"
            x-ref="drawer"
            x-bind:role="!isDesktop && drawerOpen ? 'dialog' : null"
            x-bind:aria-modal="!isDesktop && drawerOpen ? 'true' : null"
            x-bind:class="{ 'translate-x-0 pointer-events-auto': drawerOpen, '-translate-x-full pointer-events-none': !drawerOpen, 'is-animating': drawerAnimating }"
            x-on:transitionend.self="finishDrawerAnimation"
            x-on:touchstart.passive="onDrawerTouchStart($event)"
            x-on:touchend.passive="onDrawerTouchEnd($event)"
            class="drawer-panel fixed inset-y-0 left-0 z-50 flex h-svh w-72 -translate-x-full pointer-events-none flex-col overscroll-contain bg-maroon-dark text-white print:hidden lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-68 lg:shrink-0 lg:translate-x-0 lg:pointer-events-auto"
            aria-label="Navigasi samping"
        >
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-4">
                <img
                    src="{{ asset('img/favicon-tab-browser.jpg') }}"
                    alt="{{ $namaAplikasi }}"
                    width="40"
                    height="40"
                    decoding="async"
                    class="h-10 w-10 shrink-0 rounded-xl bg-white object-contain p-1"
                >
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-bold uppercase tracking-wider text-gold-soft">{{ $namaAplikasi }}</div>
                    <div data-marquee class="truncate text-sm font-bold text-white">{{ $namaProperti ?? 'Panel Manajemen' }}</div>
                </div>
                <button
                    type="button"
                    x-ref="drawerClose"
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
                                    <i class="fas fa-building w-5 shrink-0 text-center {{ request()->routeIs('properti.*') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                    <span data-marquee class="min-w-0 truncate">{{ $sudahPilihProperti ? 'Kelola / Ganti Properti' : 'Pilih Properti' }}</span>
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

                        @if ($sudahPilihProperti && $penggunaSaatIni->can('manage', [\App\Models\Pengaturan::class, $propertiAktif]))
                            <li>
                                <a
                                    href="{{ route('pengaturan.index') }}"
                                    @if (request()->routeIs('pengaturan.*')) aria-current="page" @endif
                                    class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors {{ request()->routeIs('pengaturan.*') ? 'bg-white/15 font-bold text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}"
                                >
                                    <i class="fas fa-gear w-5 text-center {{ request()->routeIs('pengaturan.*') ? 'text-gold-soft' : 'text-white/55' }}" aria-hidden="true"></i>
                                    <span>Pengaturan</span>
                                </a>
                            </li>
                        @endif

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
                        <div data-marquee class="truncate text-sm font-bold text-white">{{ $penggunaSaatIni->name }}</div>
                        <div data-marquee class="truncate text-[11px] text-white/65">{{ $penggunaSaatIni->role->label() }}</div>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Area Kanan (Topbar + Konten Penuh) --}}
        <div x-bind:inert="drawerOpen && !isDesktop" class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-line bg-surface print:hidden">
                <div class="flex h-16 min-w-0 items-center gap-2 px-3 sm:gap-3 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        x-on:click="toggleDrawer"
                        aria-controls="sidebar-nav"
                        x-bind:aria-expanded="!isDesktop && drawerOpen ? 'true' : 'false'"
                        aria-label="Buka menu navigasi"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-line-strong bg-surface text-ink hover:bg-sunken lg:hidden"
                    >
                        <i class="fas fa-bars text-base" aria-hidden="true"></i>
                    </button>

                    <span class="hidden h-9 w-1.5 shrink-0 rounded-full bg-maroon sm:block" aria-hidden="true"></span>

                    <div class="min-w-0 flex-1">
                        <h1 data-marquee class="truncate text-sm font-bold text-ink sm:text-base">{{ $judul }}</h1>
                        <p data-marquee class="truncate text-xs text-muted">{{ $subjudulHeader }}</p>
                    </div>

                    <div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
                        @isset($aksiHeader)
                            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                                {{ $aksiHeader }}
                            </div>
                        @endisset

                        {{-- Lonceng Notifikasi --}}
                        @php
                            $notifStorageKey = 'laundry_kos_notif:' . $penggunaSaatIni->id . ':' . ($propertiAktif?->id ?? 'global');
                            $notifPayload = ($daftarNotifikasi ?? collect())->take(25)->values()->all();
                        @endphp
                        <div
                            class="relative"
                            x-data="notificationMenu(@js($notifStorageKey), @js($notifPayload), @js(route('notifikasi.index')))"
                            x-on:keydown.escape.window="close"
                        >
                            <button
                                type="button"
                                x-on:click="toggle"
                                :aria-expanded="open"
                                aria-label="Notifikasi"
                                class="relative flex h-11 w-11 items-center justify-center rounded-xl border border-line-strong bg-surface text-ink hover:bg-sunken"
                            >
                                <i
                                    class="fas fa-bell text-base"
                                    :class="unreadCount > 0 ? 'text-maroon' : 'text-muted'"
                                    aria-hidden="true"
                                ></i>
                                <span
                                    x-show="unreadCount > 0"
                                    x-cloak
                                    x-text="unreadCount > 99 ? '99+' : unreadCount"
                                    class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white shadow-xs"
                                ></span>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition duration-150 ease-out"
                                x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition duration-100 ease-in"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
                                x-on:click.outside="close"
                                class="panel-mengambang fixed inset-x-3 top-[4.5rem] z-50 mx-auto w-auto max-w-sm rounded-2xl border border-line bg-surface p-2 shadow-xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mx-0 sm:mt-2 sm:w-84 sm:max-w-[calc(100vw-2rem)]"
                            >
                                <div class="flex items-center justify-between border-b border-line px-3 py-2">
                                    <span class="text-sm font-bold text-ink">Notifikasi</span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                                        :class="unreadCount > 0 ? 'bg-maroon/10 text-maroon' : 'bg-sunken text-muted'"
                                        x-text="unreadCount > 0 ? unreadCount + ' baru' : visibleItems.length + ' item'"
                                    ></span>
                                </div>

                                <div
                                    x-show="visibleItems.length > 0"
                                    class="flex items-center justify-between gap-2 border-b border-line px-2 py-1.5"
                                >
                                    <button
                                        type="button"
                                        x-on:click="markAllRead"
                                        :disabled="unreadCount === 0"
                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-bold text-maroon transition-colors hover:bg-sunken disabled:cursor-not-allowed disabled:opacity-45"
                                    >
                                        <i class="fas fa-check-double text-[10px]" aria-hidden="true"></i>
                                        <span>Tandai dibaca</span>
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="clearAll"
                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-bold text-danger transition-colors hover:bg-danger/10"
                                    >
                                        <i class="fas fa-trash-can text-[10px]" aria-hidden="true"></i>
                                        <span>Bersihkan semua</span>
                                    </button>
                                </div>

                                <div
                                    x-show="visibleItems.length === 0"
                                    class="px-3 py-6 text-center text-xs text-muted"
                                >
                                    Belum ada peringatan kuota atau notifikasi baru saat ini.
                                </div>

                                <ul
                                    x-show="visibleItems.length > 0"
                                    class="max-h-[min(18rem,calc(100svh-12rem))] space-y-1 overflow-y-auto overscroll-contain py-1"
                                >
                                    <template x-for="notif in visibleItems" :key="notif.id">
                                        <li>
                                            <a
                                                :href="notif.url"
                                                x-on:click="markRead(notif.id)"
                                                class="flex items-start gap-2.5 rounded-xl px-2.5 py-2 transition-colors hover:bg-sunken"
                                                :class="isRead(notif.id) ? '' : 'bg-maroon/5'"
                                            >
                                                <span
                                                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                                    :class="{
                                                        'bg-maroon/10 text-maroon': notif.tipe === 'activity',
                                                        'bg-danger/10 text-danger': notif.tipe === 'danger',
                                                        'bg-gold-container text-warn': notif.tipe === 'warn',
                                                        'bg-info/10 text-info': notif.tipe !== 'activity' && notif.tipe !== 'danger' && notif.tipe !== 'warn',
                                                    }"
                                                >
                                                    <i
                                                        class="fas text-xs"
                                                        :class="{
                                                            'fa-shirt': notif.tipe === 'activity',
                                                            'fa-triangle-exclamation': notif.tipe === 'danger',
                                                            'fa-circle-exclamation': notif.tipe === 'warn',
                                                            'fa-sliders': notif.tipe !== 'activity' && notif.tipe !== 'danger' && notif.tipe !== 'warn',
                                                        }"
                                                        aria-hidden="true"
                                                    ></i>
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start gap-1.5">
                                                        <p
                                                            class="min-w-0 flex-1 line-clamp-2 text-xs leading-snug text-ink"
                                                            :class="isRead(notif.id) ? 'font-medium' : 'font-bold'"
                                                            x-text="notif.judul"
                                                        ></p>
                                                        <span
                                                            x-show="!isRead(notif.id)"
                                                            class="mt-1 h-2 w-2 shrink-0 rounded-full bg-maroon"
                                                            aria-hidden="true"
                                                        ></span>
                                                    </div>
                                                    <p class="mt-0.5 text-[11px] leading-snug text-muted" x-text="notif.keterangan"></p>
                                                    <p
                                                        x-show="notif.waktu_label"
                                                        x-text="notif.waktu_label"
                                                        class="mt-0.5 text-[10px] text-subtle"
                                                    ></p>
                                                </div>
                                            </a>
                                        </li>
                                    </template>
                                </ul>

                                @if ($sudahPilihProperti)
                                    <div x-show="visibleItems.length > 0" class="border-t border-line pt-1.5">
                                        <a
                                            href="{{ route('penghuni.index') }}"
                                            class="flex min-h-10 items-center justify-center rounded-xl text-xs font-bold text-maroon hover:bg-sunken"
                                        >
                                            Lihat Semua Penghuni →
                                        </a>
                                    </div>
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
                                    <span data-marquee class="block max-w-32 truncate text-xs font-bold text-ink">{{ $penggunaSaatIni->name }}</span>
                                    <span class="block text-[10px] text-muted">{{ $penggunaSaatIni->role->label() }}</span>
                                </span>
                                <i class="fas fa-chevron-down hidden text-[10px] text-muted md:block" aria-hidden="true"></i>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition duration-150 ease-out"
                                x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition duration-100 ease-in"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
                                x-on:click.outside="close"
                                class="panel-mengambang absolute right-0 top-full z-50 mt-2 w-60 max-w-[calc(100vw-1.5rem)] rounded-2xl border border-line bg-surface p-2 shadow-xl"
                            >
                                <div class="border-b border-line px-3 py-2">
                                    <div data-marquee class="truncate text-sm font-bold text-ink">{{ $penggunaSaatIni->name }}</div>
                                    <div data-marquee class="truncate text-xs text-muted">{{ '@' . $penggunaSaatIni->username }} · {{ $penggunaSaatIni->role->label() }}</div>
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
