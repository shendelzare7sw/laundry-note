<x-app-layout
    judul="Beranda"
    :subjudul="$propertiAktif ? $propertiAktif->nama . ' · Periode ' . $rentangBulan['label'] : 'Ringkasan operasional properti'"
>
    @if ($propertiAktif)
        <x-slot:aksiHeader>
            <x-lencana :status="$propertiAktif->status" />
        </x-slot:aksiHeader>
    @endif

    <div class="space-y-4">
        @if ($propertiAktif)
            <div class="grid grid-cols-3 gap-1.5 sm:flex sm:flex-wrap sm:items-center sm:gap-2">
                <a
                    href="{{ route('laundry.create') }}"
                    class="inline-flex min-h-11 min-w-0 items-center justify-center gap-1.5 rounded-full bg-maroon px-2 text-center text-xs font-bold text-white shadow-xs transition-colors hover:bg-maroon-cerah sm:px-5 sm:text-sm"
                >
                    <i class="fas fa-plus shrink-0 text-xs" aria-hidden="true"></i>
                    <span class="sm:hidden">Catat</span>
                    <span class="hidden sm:inline">Catat Laundry</span>
                </a>
                <a
                    href="{{ route('laundry.index') }}"
                    class="inline-flex min-h-11 min-w-0 items-center justify-center gap-1.5 rounded-full border border-line-strong bg-surface px-2 text-center text-xs font-bold text-ink hover:bg-sunken sm:px-4 sm:text-sm"
                >
                    <i class="fas fa-shirt shrink-0 text-xs text-muted" aria-hidden="true"></i>
                    <span class="sm:hidden">Laundry</span>
                    <span class="hidden sm:inline">Daftar Laundry</span>
                </a>
                <a
                    href="{{ route('penghuni.index') }}"
                    class="inline-flex min-h-11 min-w-0 items-center justify-center gap-1.5 rounded-full border border-line-strong bg-surface px-2 text-center text-xs font-bold text-ink hover:bg-sunken sm:px-4 sm:text-sm"
                >
                    <i class="fas fa-users shrink-0 text-xs text-muted" aria-hidden="true"></i>
                    <span>Penghuni</span>
                </a>
            </div>

            <x-kartu judul="Informasi Properti" :subjudul="$propertiAktif->alamat ?: 'Alamat properti belum dilengkapi'">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <x-ringkasan-tile label="Total Berat Bulan Ini" :nilai="$totalBeratKg . ' kg'" :keterangan="$rentangBulan['label']" />
                    <x-ringkasan-tile label="Penghuni Mencatat" :nilai="$jumlahPenghuniMencatat . ' orang'" keterangan="Aktif bulan ini" />
                    <x-ringkasan-tile label="Biaya Utama" :nilai="'Rp' . number_format($totalBiayaUtama, 0, ',', '.')" aksen="ok" />
                    <x-ringkasan-tile label="Bayar Terpisah" :nilai="'Rp' . number_format($totalBayarTerpisah, 0, ',', '.')" aksen="gold" />
                </div>
            </x-kartu>
        @else
            <x-kartu judul="Belum Ada Properti">
                <p class="text-sm text-muted">
                    Belum ada properti yang terdaftar. Silakan tambahkan properti terlebih dahulu melalui menu Kelola Properti.
                </p>
            </x-kartu>
        @endif

        <x-kartu judul="Peringatan Kuota Bulan Ini" :subjudul="'Penghuni aktif yang mendekati atau melewati kuota (' . $rentangBulan['label'] . ')'">
            @if ($daftarPeringatan->isEmpty())
                <p class="text-sm text-muted">
                    Belum ada penghuni yang mendekati atau melewati kuota laundry pada {{ $rentangBulan['label'] }}.
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($daftarPeringatan as $item)
                        @php
                            $p = $item['penghuni'];
                            $q = $item['kuota'];
                        @endphp
                        <div class="rounded-xl border p-3.5 {{ $q['status'] === 'lewat' ? 'border-danger/30 bg-danger/5' : 'border-gold/30 bg-gold-container/50' }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-maroon/10 px-2 py-0.5 text-xs font-bold text-maroon">Kamar {{ $item['kamar'] }}</span>
                                        <a href="{{ route('penghuni.show', $p) }}" class="text-sm font-bold text-ink hover:text-maroon">
                                            {{ $p->nama }}
                                        </a>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if (! empty($item['wa_url']))
                                        <a
                                            href="{{ $item['wa_url'] }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-ok px-2.5 text-xs font-bold text-white hover:bg-ok/90"
                                        >
                                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                            <span>Kirim WA</span>
                                        </a>
                                    @endif
                                    <a
                                        href="{{ route('penghuni.show', $p) }}"
                                        class="inline-flex min-h-9 items-center rounded-lg border border-line-strong bg-surface px-2.5 text-xs font-bold text-ink hover:bg-sunken"
                                    >
                                        Detail
                                    </a>
                                </div>
                            </div>

                            <div class="mt-2.5">
                                <x-kuota-bar :kuota="$q" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-kartu>
    </div>
</x-app-layout>
