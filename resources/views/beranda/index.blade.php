<x-app-layout judul="Beranda">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-ink">Beranda</h1>
                <p class="text-sm text-muted">
                    Ringkasan operasional properti
                    @if ($propertiAktif)
                        <span class="font-medium text-ink">{{ $propertiAktif->nama }}</span>
                    @endif
                </p>
            </div>

            @if ($propertiAktif)
                <x-lencana :status="$propertiAktif->status" />
            @endif
        </div>

        @if ($propertiAktif)
            <x-kartu judul="Informasi Properti" :subjudul="$propertiAktif->alamat ?: 'Alamat properti belum dilengkapi'">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <x-ringkasan-tile label="Total Berat Bulan Ini" nilai="0,0 kg" keterangan="Menunggu Fase 3" />
                    <x-ringkasan-tile label="Penghuni Mencatat" nilai="0 orang" keterangan="Menunggu Fase 2–3" />
                    <x-ringkasan-tile label="Biaya Utama" nilai="Rp0" aksen="ok" />
                    <x-ringkasan-tile label="Bayar Terpisah" nilai="Rp0" aksen="gold" />
                </div>
            </x-kartu>
        @else
            <x-kartu judul="Belum Ada Properti">
                <p class="text-sm text-muted">
                    Belum ada properti yang terdaftar. Silakan tambahkan properti terlebih dahulu melalui menu Kelola Properti.
                </p>
            </x-kartu>
        @endif

        <x-kartu judul="Peringatan Kuota Bulan Ini" subjudul="Penghuni yang mendekati atau melewati kuota 20 kg">
            <p class="text-sm text-muted">
                Daftar peringatan kuota akan aktif setelah modul Penghuni dan Kuota (Fase 2) serta Catatan Laundry (Fase 3) selesai dibangun.
            </p>
        </x-kartu>
    </div>
</x-app-layout>
