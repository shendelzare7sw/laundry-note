@php
    $menuUtama = [
        ['route' => 'beranda', 'label' => 'Beranda', 'aktif' => request()->routeIs('beranda'), 'ikon' => 'home'],
        ['route' => 'laundry.index', 'label' => 'Laundry', 'aktif' => request()->routeIs('laundry.*'), 'ikon' => 'laundry'],
        ['route' => 'penghuni.index', 'label' => 'Penghuni', 'aktif' => request()->routeIs('penghuni.*'), 'ikon' => 'users'],
        ['route' => 'laporan.index', 'label' => 'Laporan', 'aktif' => request()->routeIs('laporan.*'), 'ikon' => 'doc'],
        ['route' => 'lainnya.index', 'label' => 'Lainnya', 'aktif' => request()->routeIs('lainnya.*', 'properti.*', 'pengguna.*'), 'ikon' => 'more'],
    ];
@endphp

<nav
    aria-label="Navigasi bawah"
    class="bottom-nav-safe fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 backdrop-blur print:hidden lg:hidden"
>
    <ul class="mx-auto grid max-w-lg grid-cols-5">
        @foreach ($menuUtama as $item)
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    @if ($item['aktif']) aria-current="page" @endif
                    class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 py-1.5 text-[11px] font-medium transition-colors {{ $item['aktif'] ? 'text-maroon font-bold' : 'text-muted hover:text-ink' }}"
                >
                    @switch($item['ikon'])
                        @case('home')
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z" />
                            </svg>
                            @break
                        @case('laundry')
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2" />
                            </svg>
                            @break
                        @case('users')
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                            </svg>
                            @break
                        @case('doc')
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" />
                            </svg>
                            @break
                        @default
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                    @endswitch
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
