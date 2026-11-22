@php
    $penggunaSaatIni = auth()->user();
    $sudahPilihProperti = ($propertiAktif ?? null) !== null || $penggunaSaatIni->role->isStafProperti();

    $menuBawah = $sudahPilihProperti
        ? [
            ['route' => 'beranda', 'label' => 'Beranda', 'aktif' => request()->routeIs('beranda'), 'ikon' => 'fa-house'],
            ['route' => 'laundry.index', 'label' => 'Laundry', 'aktif' => request()->routeIs('laundry.*'), 'ikon' => 'fa-shirt'],
            ['route' => 'penghuni.index', 'label' => 'Penghuni', 'aktif' => request()->routeIs('penghuni.*'), 'ikon' => 'fa-users'],
            ['route' => 'laporan.index', 'label' => 'Laporan', 'aktif' => request()->routeIs('laporan.*'), 'ikon' => 'fa-file-lines'],
        ]
        : [
            ['route' => 'properti.index', 'label' => 'Properti', 'aktif' => request()->routeIs('properti.*'), 'ikon' => 'fa-building'],
            ['route' => 'pengguna.index', 'label' => 'Pengguna', 'aktif' => request()->routeIs('pengguna.*'), 'ikon' => 'fa-user-gear'],
            ['route' => 'lainnya.index', 'label' => 'Lainnya', 'aktif' => request()->routeIs('lainnya.*'), 'ikon' => 'fa-sliders'],
        ];

    $gridCols = count($menuBawah) === 4 ? 'grid-cols-5' : 'grid-cols-4';
@endphp

<nav
    aria-label="Navigasi bawah"
    x-bind:inert="drawerOpen && !isDesktop"
    class="bottom-nav-bar bottom-nav-safe fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface shadow-[0_-1px_6px_rgb(28_28_28/0.04)] print:hidden lg:hidden"
>
    <ul class="grid {{ $gridCols }}">
        @foreach ($menuBawah as $item)
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    @if ($item['aktif']) aria-current="page" @endif
                    class="flex min-h-14 flex-col items-center justify-center gap-1 px-1 py-1.5 text-[11px] font-medium transition-colors {{ $item['aktif'] ? 'font-bold text-maroon' : 'text-muted hover:text-ink' }}"
                >
                    <i class="fas {{ $item['ikon'] }} text-base" aria-hidden="true"></i>
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach

        <li>
            <button
                type="button"
                x-on:click="toggleDrawer"
                aria-controls="sidebar-nav"
                x-bind:aria-expanded="!isDesktop && drawerOpen ? 'true' : 'false'"
                class="flex min-h-14 w-full flex-col items-center justify-center gap-1 px-1 py-1.5 text-[11px] font-medium text-muted transition-colors hover:text-ink"
            >
                <i class="fas fa-bars text-base" aria-hidden="true"></i>
                <span>Menu</span>
            </button>
        </li>
    </ul>
</nav>
