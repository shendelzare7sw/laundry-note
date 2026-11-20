<x-app-layout judul="Penghuni" :subjudul="$propertiAktif->nama . ' · Periode kuota ' . $rentangBulan['label']">
    @if ($bolehKelolaKontrak)
        <x-slot:aksiHeader>
            <button
                type="button"
                data-modal-open="modal-penghuni-masuk"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-maroon px-3.5 text-xs font-bold text-white hover:bg-maroon-cerah sm:px-5 sm:text-sm"
            >
                <i class="fas fa-user-plus text-xs" aria-hidden="true"></i>
                <span class="hidden sm:inline">Penghuni Masuk</span>
                <span class="sm:hidden">Masuk</span>
            </button>
        </x-slot:aksiHeader>
    @endif

    <div class="space-y-4">
        @if (! $bolehKelolaKontrak)
            <x-kartu class="border-gold/30 bg-gold-container/60" padding="px-4 py-3">
                <p class="text-xs text-on-gold sm:text-sm">
                    <i class="fas fa-lock mr-1.5" aria-hidden="true"></i>
                    Akses kelola kontrak (masuk, pindah kamar, keluar) saat ini dikunci untuk penjaga. Anda dapat melihat detail penghuni dan mengirim pesan WhatsApp.
                </p>
            </x-kartu>
        @endif

        @if ($errors->any())
            <x-kartu class="border-danger/30 bg-danger/5">
                <ul class="list-disc space-y-1 pl-5 text-sm text-danger">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </x-kartu>
        @endif

        <x-kartu>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <nav aria-label="Filter status penghuni" class="flex flex-wrap gap-2">
                    @foreach ([
                        'aktif' => ['label' => 'Aktif', 'jumlah' => $jumlahAktif],
                        'keluar' => ['label' => 'Keluar', 'jumlah' => $jumlahKeluar],
                        'semua' => ['label' => 'Semua', 'jumlah' => $jumlahSemua],
                    ] as $kodeTab => $infoTab)
                        @php $sedangAktif = $tab === $kodeTab; @endphp
                        <a
                            href="{{ route('penghuni.index', array_filter(['tab' => $kodeTab, 'cari' => $cari !== '' ? $cari : null, 'bulan' => $rentangBulan['bulan']])) }}"
                            class="inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-medium transition {{ $sedangAktif ? 'border-maroon bg-maroon text-white' : 'border-line-strong bg-surface text-ink hover:bg-sunken' }}"
                        >
                            <span>{{ $infoTab['label'] }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $sedangAktif ? 'bg-white/20 text-white' : 'bg-sunken text-muted' }}">
                                {{ $infoTab['jumlah'] }}
                            </span>
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ route('penghuni.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="relative min-w-[14rem] flex-1 sm:w-64">
                        <label for="cari-penghuni" class="sr-only">Cari penghuni</label>
                        <input
                            id="cari-penghuni"
                            type="search"
                            name="cari"
                            value="{{ $cari }}"
                            placeholder="Cari nama, panggilan, kamar, WA..."
                            class="min-h-11 w-full rounded-xl border border-line-strong bg-sunken/70 px-3.5 text-sm text-ink placeholder:text-subtle focus:border-maroon focus:bg-surface focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        >
                    </div>
                    <div>
                        <label for="filter-bulan-penghuni" class="sr-only">Bulan kuota</label>
                        <input
                            id="filter-bulan-penghuni"
                            type="month"
                            name="bulan"
                            value="{{ $rentangBulan['bulan'] }}"
                            class="min-h-11 rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        >
                    </div>
                    <button
                        type="submit"
                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                    >
                        <i class="fas fa-magnifying-glass text-xs" aria-hidden="true"></i>
                        <span>Terapkan</span>
                    </button>
                    @if ($cari !== '')
                        <a
                            href="{{ route('penghuni.index', ['tab' => $tab, 'bulan' => $rentangBulan['bulan']]) }}"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line-strong px-3.5 text-sm text-ink hover:bg-sunken"
                        >
                            Reset
                        </a>
                    @endif
                </form>
            </div>
        </x-kartu>

        @if ($daftarPenghuni->isEmpty())
            <x-kartu class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-sunken text-muted">
                    <i class="fas fa-users text-lg" aria-hidden="true"></i>
                </div>
                <h2 class="mt-3 text-base font-bold text-ink">Belum ada penghuni yang sesuai filter</h2>
                <p class="mt-1 text-sm text-muted">Coba ubah tab status atau kata kunci pencarian Anda.</p>
            </x-kartu>
        @else
            <div class="grid gap-3 md:hidden">
                @foreach ($daftarPenghuni as $penghuni)
                    @php
                        $kamarTerkini = $penghuni->penempatanAktif?->kamar ?? $penghuni->penempatan->first()?->kamar;
                        $kuota = $ringkasanKuota->get($penghuni->id);
                        $statusLencana = $penghuni->status === 'keluar' ? 'keluar' : ($kuota['status'] ?? 'normal');
                    @endphp
                    <x-kartu class="{{ ($kuota['status'] ?? 'normal') === 'lewat' ? 'border-danger/35' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-lg bg-maroon/10 px-2.5 py-1 text-xs font-bold text-maroon">
                                        Kamar {{ $kamarTerkini?->nomor ?? '-' }}
                                    </span>
                                    <x-lencana :status="$statusLencana" />
                                </div>
                                <a href="{{ route('penghuni.show', ['penghuni' => $penghuni, 'bulan' => $rentangBulan['bulan']]) }}" class="mt-2 block text-base font-bold text-ink hover:text-maroon">
                                    {{ $penghuni->nama }}
                                </a>
                                @if ($penghuni->alias)
                                    <p class="text-xs text-muted">Panggilan: {{ $penghuni->alias }}</p>
                                @endif
                            </div>

                            @if ($kuota && $kuota['total_tagihan'] > 0)
                                <div class="shrink-0 text-right">
                                    <span class="block text-[11px] text-muted">Tagihan</span>
                                    <span class="text-sm font-bold text-danger">Rp{{ number_format($kuota['total_tagihan'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>

                        @if ($kuota)
                            <div class="mt-3">
                                <x-kuota-bar :kuota="$kuota" :ringkas="true" />
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                            <a
                                href="{{ route('penghuni.show', ['penghuni' => $penghuni, 'bulan' => $rentangBulan['bulan']]) }}"
                                class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border border-line-strong px-3 text-sm font-bold text-ink hover:bg-sunken"
                            >
                                <i class="fas fa-id-card text-xs" aria-hidden="true"></i>
                                <span>Detail & Kontrak</span>
                            </a>

                            @if ($penghuni->no_wa)
                                <a
                                    href="https://wa.me/{{ $penghuni->no_wa }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-ok px-4 text-sm font-bold text-white hover:bg-ok/90"
                                >
                                    <i class="fab fa-whatsapp text-base" aria-hidden="true"></i>
                                    <span>WA</span>
                                </a>
                            @endif
                        </div>
                    </x-kartu>
                @endforeach
            </div>

            <x-kartu padding="p-0 overflow-hidden" class="hidden md:block">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead class="border-b border-line bg-alt text-xs font-bold uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-3">Kamar</th>
                                <th scope="col" class="px-4 py-3">Penghuni</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="min-w-[15rem] px-4 py-3">Kuota {{ $rentangBulan['label'] }}</th>
                                <th scope="col" class="px-4 py-3">Tagihan</th>
                                <th scope="col" class="px-4 py-3">WhatsApp</th>
                                <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($daftarPenghuni as $penghuni)
                                @php
                                    $kamarTerkini = $penghuni->penempatanAktif?->kamar ?? $penghuni->penempatan->first()?->kamar;
                                    $kuota = $ringkasanKuota->get($penghuni->id);
                                    $statusLencana = $penghuni->status === 'keluar' ? 'keluar' : ($kuota['status'] ?? 'normal');
                                    $isLewat = ($kuota['status'] ?? 'normal') === 'lewat';
                                @endphp
                                <tr class="{{ $isLewat ? 'bg-danger/5 hover:bg-danger/10' : 'hover:bg-alt' }}">
                                    <td class="whitespace-nowrap px-4 py-3 align-middle">
                                        <span class="inline-flex items-center rounded-lg bg-maroon/10 px-2.5 py-1 text-xs font-bold text-maroon">
                                            {{ $kamarTerkini?->nomor ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 align-middle">
                                        <a href="{{ route('penghuni.show', ['penghuni' => $penghuni, 'bulan' => $rentangBulan['bulan']]) }}" class="font-bold text-ink hover:text-maroon">
                                            {{ $penghuni->nama }}
                                        </a>
                                        <div class="mt-0.5 text-xs text-muted">
                                            @if ($penghuni->alias)
                                                <span>Alias: {{ $penghuni->alias }}</span>
                                                <span class="mx-1">·</span>
                                            @endif
                                            @if ($penghuni->status === 'keluar' && $penghuni->tanggal_keluar)
                                                <span>Keluar {{ $penghuni->tanggal_keluar->format('d/m/Y') }}</span>
                                            @else
                                                <span>Masuk {{ $penghuni->tanggal_masuk->format('d/m/Y') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle">
                                        <x-lencana :status="$statusLencana" />
                                    </td>
                                    <td class="px-4 py-3 align-middle">
                                        @if ($kuota)
                                            <x-kuota-bar :kuota="$kuota" :ringkas="true" />
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle">
                                        @if ($kuota && $kuota['total_tagihan'] > 0)
                                            <span class="font-bold {{ $isLewat ? 'text-danger' : 'text-ink' }}">
                                                Rp{{ number_format($kuota['total_tagihan'], 0, ',', '.') }}
                                            </span>
                                            @if ($kuota['nominal_penyesuaian'] !== null)
                                                <span class="block text-[11px] text-muted">Manual</span>
                                            @endif
                                        @else
                                            <span class="text-xs text-muted">Rp0</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle">
                                        @if ($penghuni->no_wa)
                                            <a
                                                href="https://wa.me/{{ $penghuni->no_wa }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-ok/10 px-3 text-xs font-bold text-ok hover:bg-ok hover:text-white"
                                            >
                                                <i class="fab fa-whatsapp text-sm" aria-hidden="true"></i>
                                                <span>{{ $penghuni->no_wa }}</span>
                                            </a>
                                        @else
                                            <span class="text-xs text-subtle">Belum ada</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right align-middle">
                                        <a
                                            href="{{ route('penghuni.show', ['penghuni' => $penghuni, 'bulan' => $rentangBulan['bulan']]) }}"
                                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line-strong px-3.5 text-xs font-bold text-ink hover:bg-sunken"
                                        >
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-kartu>
        @endif
    </div>

    @if ($bolehKelolaKontrak)
        <x-modal id="modal-penghuni-masuk" judul="Daftarkan Penghuni Masuk">
            @if ($kamarKosong->isEmpty())
                <div class="rounded-xl border border-gold/30 bg-gold-container px-4 py-3 text-sm text-on-gold">
                    Semua kamar aktif saat ini sedang ditempati. Kosongkan kamar melalui aksi Keluar pada penghuni terkait terlebih dahulu.
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken">
                        Tutup
                    </button>
                </div>
            @else
                <form
                    method="POST"
                    action="{{ route('penghuni.store') }}"
                    x-data="kontrakSewa({
                        status: @js(old('status', 'aktif')),
                        tanggalMasuk: @js(old('tanggal_masuk', now('Asia/Jakarta')->toDateString())),
                        jangkaWaktu: @js(old('jangka_waktu_sewa', 'bulanan')),
                        tanggalKeluarRencana: @js(old('tanggal_keluar_rencana', \App\Models\Penghuni::hitungTanggalKeluarRencana(old('tanggal_masuk', now('Asia/Jakarta')->toDateString()), old('jangka_waktu_sewa', 'bulanan')) ?? ''))
                    })"
                    data-track-dirty
                    data-draft-key="penghuni-masuk-{{ $propertiAktif->id }}"
                    data-confirm-title="Simpan Penghuni & Kontrak Baru?"
                    data-confirm-message="Penghuni baru akan langsung tercatat pada kamar yang dipilih sesuai status kontraknya."
                    data-confirm-label="Ya, Daftarkan"
                    data-confirm-variant="maroon"
                    class="space-y-4"
                >
                    @csrf
                    <x-field label="Nama Lengkap" name="nama" id="masuk-nama" :required="true" placeholder="Mis. Riko Pratama" />
                    <x-field label="Nama Panggilan / Alias" name="alias" id="masuk-alias" placeholder="Opsional, membantu pencarian catatan" />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Status Kontrak" name="status" id="masuk-status" :required="true">
                            <select
                                id="masuk-status"
                                name="status"
                                x-model="status"
                                required
                                class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                            >
                                <option value="aktif">Aktif</option>
                                <option value="booking">Booking</option>
                            </select>
                        </x-field>

                        <x-field label="Kamar Tujuan" name="kamar_id" id="masuk-kamar" :required="true">
                            <select
                                id="masuk-kamar"
                                name="kamar_id"
                                required
                                class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                            >
                                <option value="">-- Pilih Kamar Kosong --</option>
                                @foreach ($kamarKosong as $kamar)
                                    <option value="{{ $kamar->id }}" @selected((string) old('kamar_id') === (string) $kamar->id)>
                                        Kamar {{ $kamar->nomor }} (Lantai {{ $kamar->lantai }})
                                    </option>
                                @endforeach
                            </select>
                        </x-field>
                    </div>

                    <x-field
                        label="Tanggal Masuk"
                        name="tanggal_masuk"
                        id="masuk-tanggal"
                        type="date"
                        x-model="tanggalMasuk"
                        x-on:change="saatUbahTanggalMasuk"
                        :value="old('tanggal_masuk', now('Asia/Jakarta')->toDateString())"
                        :required="true"
                    />

                    <label class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3.5 py-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            name="tanggal_masuk_perkiraan"
                            value="1"
                            @checked((bool) old('tanggal_masuk_perkiraan', false))
                            class="h-4 w-4 rounded border-line-strong text-maroon focus:ring-maroon"
                        >
                        <span>Tanggal masuk merupakan perkiraan</span>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Jangka Waktu Sewa" name="jangka_waktu_sewa" id="masuk-jangka-waktu" :required="true">
                            <select
                                id="masuk-jangka-waktu"
                                name="jangka_waktu_sewa"
                                x-model="jangkaWaktu"
                                x-on:change="saatUbahJangkaWaktu"
                                class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                            >
                                @foreach (\App\Models\Penghuni::DAFTAR_JANGKA_WAKTU as $kodeJangka => $labelJangka)
                                    <option value="{{ $kodeJangka }}">{{ $labelJangka }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field
                            label="Tgl Keluar Rencana"
                            name="tanggal_keluar_rencana"
                            id="masuk-keluar-rencana"
                            type="date"
                            x-model="tanggalKeluarRencana"
                            hint="Otomatis terhitung untuk Bulanan–1 Tahun; boleh dikosongkan / custom jika Tidak Tentu."
                        />
                    </div>

                    <x-field
                        label="Nomor WhatsApp"
                        name="no_wa"
                        id="masuk-wa"
                        type="tel"
                        placeholder="0812xxxxxxxx"
                        hint="Boleh memakai awalan 08 atau 62; otomatis dinormalisasi ke 62."
                    />

                    <x-field label="Catatan" name="catatan" id="masuk-catatan" placeholder="Opsional" />

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken">
                            Batal
                        </button>
                        <button type="submit" class="min-h-11 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah">
                            Daftarkan Penghuni
                        </button>
                    </div>
                </form>
            @endif
        </x-modal>
    @endif
</x-app-layout>
