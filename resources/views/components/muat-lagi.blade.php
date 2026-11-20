{{--
    Footer daftar pengganti pagination bernomor: "Menampilkan X dari Y" + tombol "Lihat lebih banyak".
    Setelah dimuat, layar langsung menggulir ke data pertama yang baru: item daftar memakai id m-{no}
    (kartu ponsel / daftar tunggal) dan d-{no} (baris tabel desktop).
--}}
@props([
    'items',
    'langkah' => \App\Support\Tampil::LANGKAH,
    'satuDaftar' => false,
])
@php
    $ditampilkan = $items->count();
    $total = $items->total();
    $sisa = max(0, $total - $ditampilkan);
    $url = request()->fullUrlWithQuery(['tampil' => $ditampilkan + $langkah]);
    $mentok = $ditampilkan >= \App\Support\Tampil::MAKS;
@endphp

@if ($total > 0)
    <footer
        id="muat-lagi"
        class="flex flex-col items-stretch gap-3 rounded-2xl border border-line bg-surface px-4 py-3.5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:px-5"
    >
        <p class="text-center text-xs text-muted sm:text-left">
            Menampilkan <strong class="text-ink">{{ $ditampilkan }}</strong> dari <strong class="text-ink">{{ $total }}</strong> data
        </p>

        @if ($sisa > 0 && ! $mentok)
            <a
                href="{{ $url }}"
                x-data="{ sibuk: false }"
                :class="sibuk && 'pointer-events-none opacity-60'"
                x-on:click.prevent="sibuk = true; window.location.href = @js($url) + '#' + ({{ $satuDaftar ? 'true' : 'false' }} || window.innerWidth < 1024 ? 'm' : 'd') + '-{{ $ditampilkan + 1 }}'"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-maroon/10 px-4 text-sm font-bold text-maroon transition hover:bg-maroon/15"
            >
                <i class="fas fa-spinner fa-spin text-xs" x-show="sibuk" x-cloak aria-hidden="true"></i>
                <i class="fas fa-angles-down text-xs" x-show="!sibuk" aria-hidden="true"></i>
                <span>Lihat lebih banyak ({{ min($langkah, $sisa) }} lagi)</span>
            </a>
        @elseif ($sisa > 0)
            <p class="text-center text-xs font-bold text-on-gold">
                Gunakan pencarian atau filter periode untuk mempersempit daftar.
            </p>
        @elseif ($total > $langkah)
            <p class="text-center text-xs font-bold text-ok">
                Semua data sudah ditampilkan.
            </p>
        @endif
    </footer>
@endif
