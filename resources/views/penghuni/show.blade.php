<x-app-layout judul="Detail Penghuni" :subjudul="$penghuni->nama . ' · ' . $propertiAktif->nama">
    @php
        $kamarTerkini = $penghuni->penempatanAktif?->kamar ?? $penghuni->penempatan->first()?->kamar;
        $isLewat = $kuotaBulanIni['status'] === 'lewat';
    @endphp

    <x-slot:aksiHeader>
        <a
            href="{{ route('penghuni.index', ['bulan' => $kuotaBulanIni['bulan']]) }}"
            class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line-strong bg-surface px-3 text-xs font-medium text-ink hover:bg-sunken sm:px-3.5 sm:text-sm"
        >
            <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i>
            <span class="hidden sm:inline">Kembali ke Daftar</span>
        </a>
    </x-slot:aksiHeader>

    <div class="space-y-4">
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
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-lg bg-maroon px-3 py-1 text-xs font-bold text-white">
                            Kamar {{ $kamarTerkini?->nomor ?? '-' }}
                        </span>
                        <x-lencana :status="$penghuni->status" />
                        @if ($penghuni->status !== 'keluar')
                            <x-lencana :status="$kuotaBulanIni['status']" />
                        @endif
                        @if ($penghuni->tanggal_masuk_perkiraan)
                            <span class="inline-flex items-center rounded-full border border-line-strong bg-sunken px-2.5 py-0.5 text-xs text-muted">
                                Tanggal masuk perkiraan
                            </span>
                        @endif
                    </div>

                    <div>
                        <h2 class="text-2xl font-bold text-ink">{{ $penghuni->nama }}</h2>
                        @if ($penghuni->alias)
                            <p class="text-sm text-muted">Nama panggilan / alias: {{ $penghuni->alias }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted sm:text-sm">
                        <span>
                            <i class="fas fa-calendar-check mr-1" aria-hidden="true"></i>
                            Masuk: <strong class="text-ink">{{ $penghuni->tanggal_masuk->format('d/m/Y') }}</strong>
                        </span>

                        <span>
                            <i class="fas fa-clock mr-1" aria-hidden="true"></i>
                            Sewa: <strong class="text-ink">{{ $penghuni->labelJangkaWaktu() }}</strong>
                        </span>

                        @if ($penghuni->tanggal_keluar_rencana)
                            <span>
                                <i class="fas fa-calendar-day mr-1" aria-hidden="true"></i>
                                Keluar Rencana: <strong class="text-ink">{{ $penghuni->tanggal_keluar_rencana->format('d/m/Y') }}</strong>
                            </span>
                        @endif

                        @if ($penghuni->status === 'keluar' && $penghuni->tanggal_keluar)
                            <span class="text-danger">
                                <i class="fas fa-door-open mr-1" aria-hidden="true"></i>
                                Selesai / Keluar: <strong>{{ $penghuni->tanggal_keluar->format('d/m/Y') }}</strong>
                                @if ($penghuni->alasan_keluar)
                                    ({{ $penghuni->alasan_keluar }})
                                @endif
                            </span>
                        @endif

                        <span>
                            <i class="fab fa-whatsapp mr-1" aria-hidden="true"></i>
                            WA: <strong class="text-ink">{{ $penghuni->no_wa ?? 'Belum diisi' }}</strong>
                        </span>
                    </div>

                    @if ($penghuni->catatan)
                        <p class="rounded-xl bg-alt px-3 py-2 text-xs text-muted sm:text-sm">
                            <span class="font-bold text-ink">Catatan:</span> {{ $penghuni->catatan }}
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @if (! empty($waTagihan['url']))
                        <a
                            href="{{ $waTagihan['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-ok px-4 text-sm font-bold text-white hover:bg-ok/90"
                        >
                            <i class="fab fa-whatsapp text-base" aria-hidden="true"></i>
                            <span>Kirim Tagihan WA</span>
                        </a>
                    @elseif ($penghuni->no_wa)
                        <a
                            href="https://wa.me/{{ $penghuni->no_wa }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-ok px-4 text-sm font-bold text-white hover:bg-ok/90"
                        >
                            <i class="fab fa-whatsapp text-base" aria-hidden="true"></i>
                            <span>Chat WhatsApp</span>
                        </a>
                    @endif

                    @if (! empty($waPeringatan['url']))
                        <a
                            href="{{ $waPeringatan['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-gold-container px-4 text-sm font-bold text-on-gold hover:bg-gold/25"
                        >
                            <i class="fab fa-whatsapp text-sm" aria-hidden="true"></i>
                            <span>Peringatan Kuota</span>
                        </a>
                    @endif

                    @if ($bolehKelolaPenghuni)
                        <button
                            type="button"
                            data-modal-open="modal-ubah-data"
                            class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken"
                        >
                            <i class="fas fa-pen text-xs" aria-hidden="true"></i>
                            <span>Ubah Data</span>
                        </button>

                        <button
                            type="button"
                            data-modal-open="modal-ganti-nomor"
                            class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken"
                        >
                            <i class="fas fa-phone text-xs" aria-hidden="true"></i>
                            <span>Ganti Nomor</span>
                        </button>
                    @endif

                    @if ($bolehKelolaKontrak && in_array($penghuni->status, ['aktif', 'booking'], true))
                        <button
                            type="button"
                            data-modal-open="modal-pindah-kamar"
                            class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-gold px-4 text-sm font-bold text-white hover:bg-gold-bright"
                        >
                            <i class="fas fa-right-left text-xs" aria-hidden="true"></i>
                            <span>Pindah Kamar</span>
                        </button>

                        @if ($bisaBatalkanPindah)
                            <form
                                method="POST"
                                action="{{ route('penghuni.batalkan-pindah', $penghuni) }}"
                                data-confirm-title="Batalkan Perpindahan Kamar?"
                                data-confirm-message="Penempatan baru akan dihapus dan penghuni dikembalikan ke kamar sebelumnya selama belum ada laundry di kamar baru."
                                data-confirm-label="Ya, Batalkan Pindah"
                                data-confirm-variant="gold"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-gold-container px-4 text-sm font-bold text-on-gold hover:bg-gold/25"
                                >
                                    <i class="fas fa-rotate-left text-xs" aria-hidden="true"></i>
                                    <span>Batalkan Pindah</span>
                                </button>
                            </form>
                        @endif

                        <button
                            type="button"
                            data-modal-open="modal-keluar"
                            class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-danger/40 bg-danger/10 px-4 text-sm font-bold text-danger hover:bg-danger hover:text-white"
                        >
                            <i class="fas fa-door-open text-xs" aria-hidden="true"></i>
                            <span>Tandai Keluar</span>
                        </button>
                    @elseif ($bolehKelolaKontrak && $penghuni->status === 'keluar')
                        <form
                            method="POST"
                            action="{{ route('penghuni.batalkan-keluar', $penghuni) }}"
                            data-confirm-title="Batalkan Status Keluar?"
                            data-confirm-message="{{ $penghuni->nama }} akan diaktifkan kembali di kamar {{ $kamarTerkini?->nomor ?? '-' }} jika kamar masih kosong."
                            data-confirm-label="Ya, Aktifkan Kembali"
                            data-confirm-variant="maroon"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                            >
                                <i class="fas fa-rotate-left text-xs" aria-hidden="true"></i>
                                <span>Batalkan Keluar</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </x-kartu>

        <div class="grid gap-4 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-7">
                <x-kartu :judul="'Kuota & Tagihan — ' . $kuotaBulanIni['label_bulan']" subjudul="Dihitung per penghuni per bulan kalender tanpa reset saat pindah kamar">
                    <x-slot:aksi>
                        <form method="GET" action="{{ route('penghuni.show', $penghuni) }}" class="flex items-center gap-2">
                            <label for="pilih-bulan-detail" class="sr-only">Pilih bulan</label>
                            <input
                                id="pilih-bulan-detail"
                                type="month"
                                name="bulan"
                                value="{{ $kuotaBulanIni['bulan'] }}"
                                class="min-h-11 rounded-xl border border-line-strong bg-surface px-3 text-xs text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                            >
                            <button type="submit" class="min-h-11 rounded-xl border border-line-strong px-3 text-xs font-bold text-ink hover:bg-sunken">
                                Lihat
                            </button>
                        </form>
                    </x-slot:aksi>

                    <div class="space-y-4">
                        <x-kuota-bar :kuota="$kuotaBulanIni" />

                        <div class="grid gap-2 sm:grid-cols-2">
                            <x-batang
                                tipe="biaya"
                                label="Nilai Laundry Utama"
                                :catatan="$kuotaBulanIni['jumlah_catatan'] . ' catatan'"
                                :nilai="'Rp' . number_format($kuotaBulanIni['total_biaya_utama'], 0, ',', '.')"
                            />
                            <x-batang
                                tipe="lewat"
                                label="Tagihan Lewat Kuota"
                                :catatan="$kuotaBulanIni['nominal_penyesuaian'] !== null ? 'manual' : ($kuotaBulanIni['di_bawah_toleransi'] ? 'di bawah toleransi' : 'otomatis')"
                                :nilai="'Rp' . number_format($kuotaBulanIni['tagihan_lewat_kuota'], 0, ',', '.')"
                            />
                            <x-batang
                                tipe="terpisah"
                                label="Item Bayar Terpisah"
                                :nilai="'Rp' . number_format($kuotaBulanIni['bayar_terpisah'], 0, ',', '.')"
                            />
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-maroon/25 bg-maroon/10 px-3.5 py-2.5 text-sm font-bold text-maroon">
                                <span>Total Tagihan Penghuni</span>
                                <span>Rp{{ number_format($kuotaBulanIni['total_tagihan'], 0, ',', '.') }}</span>
                            </div>
                        </div>

                        @if ($kuotaBulanIni['catatan_penyesuaian'])
                            <p class="text-xs text-muted">
                                Catatan penyesuaian manual: <span class="font-medium text-ink">{{ $kuotaBulanIni['catatan_penyesuaian'] }}</span>
                            </p>
                        @endif

                        @if ($bolehSesuaikanKuota && $kuotaBulanIni['mode_kuota'] !== 'tanpa_kuota')
                            <div class="flex justify-end pt-1">
                                <button
                                    type="button"
                                    data-modal-open="modal-penyesuaian-kuota"
                                    class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-line-strong px-4 text-xs font-bold text-ink hover:bg-sunken"
                                >
                                    <i class="fas fa-sliders text-xs" aria-hidden="true"></i>
                                    <span>Sesuaikan Tagihan Lewat Kuota</span>
                                </button>
                            </div>
                        @endif
                    </div>
                </x-kartu>

                <x-kartu judul="Riwayat Kuota 6 Bulan Terakhir">
                    <div class="space-y-3">
                        @foreach ($riwayatKuota as $riwayat)
                            <div class="rounded-xl border px-3.5 py-3 {{ $riwayat['status'] === 'lewat' ? 'border-danger/30 bg-danger/5' : 'border-line bg-alt' }}">
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <a
                                        href="{{ route('penghuni.show', ['penghuni' => $penghuni, 'bulan' => $riwayat['bulan']]) }}"
                                        class="font-bold text-ink hover:text-maroon"
                                    >
                                        {{ $riwayat['label_bulan'] }}
                                    </a>
                                    <span class="font-bold {{ $riwayat['total_tagihan'] > 0 ? 'text-danger' : 'text-muted' }}">
                                        Total Tagihan: Rp{{ number_format($riwayat['total_tagihan'], 0, ',', '.') }}
                                    </span>
                                </div>
                                <x-kuota-bar :kuota="$riwayat" :ringkas="true" />
                            </div>
                        @endforeach
                    </div>
                </x-kartu>

                <x-kartu judul="Catatan Laundry Terbaru" subjudul="25 catatan terakhir (semua kamar)">
                    @if ($penghuni->status !== 'booking' && auth()->user()->can('create', [\App\Models\Laundry::class, $propertiAktif]))
                        <x-slot:aksi>
                            <a
                                href="{{ route('laundry.create', ['penghuni_id' => $penghuni->id]) }}"
                                class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-maroon px-3.5 text-xs font-bold text-white hover:bg-maroon-cerah"
                            >
                                <i class="fas fa-plus text-[10px]" aria-hidden="true"></i>
                                <span>Catat Laundry</span>
                            </a>
                        </x-slot:aksi>
                    @endif

                    @if ($catatanLaundry->isEmpty())
                        <p class="rounded-xl border border-dashed border-line-strong bg-alt px-4 py-6 text-center text-sm text-muted">
                            Belum ada catatan laundry untuk penghuni ini.
                        </p>
                    @else
                        <div class="space-y-2">
                            @foreach ($catatanLaundry as $item)
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-sm {{ $isLewat ? 'border-danger/30 bg-danger/5' : 'border-line bg-alt' }}">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-bold text-ink">{{ \Carbon\CarbonImmutable::parse($item->tanggal)->format('d/m/Y') }}</span>
                                            @if ($item->kamar)
                                                <span class="rounded bg-sunken px-2 py-0.5 text-xs font-medium text-ink">Kamar {{ $item->kamar->nomor }}</span>
                                            @endif
                                            @if ($isLewat)
                                                <span class="rounded bg-danger/10 px-2 py-0.5 text-[11px] font-bold text-danger">Bulan lewat kuota</span>
                                            @endif
                                        </div>
                                        @if ($item->item->isNotEmpty() || $item->catatan)
                                            <p class="mt-0.5 text-xs text-muted">
                                                {{ $item->item->isNotEmpty() ? 'Item terpisah: '.$item->ringkasanItem() : '' }}
                                                {{ $item->item->isNotEmpty() && $item->catatan ? ' · ' : '' }}
                                                {{ $item->catatan ?? '' }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2.5 text-right text-xs">
                                        <div>
                                            @if ($item->berat_kg !== null)
                                                <span class="block font-bold text-ink">{{ str_replace('.', ',', rtrim(rtrim((string) $item->berat_kg, '0'), '.')) }} kg</span>
                                            @endif
                                            @if ($item->biaya_utama !== null && $item->biaya_utama > 0)
                                                <span class="block text-muted">Rp{{ number_format($item->biaya_utama, 0, ',', '.') }}</span>
                                            @endif
                                            @if ($item->item_biaya > 0)
                                                <span class="block font-bold text-on-gold">Terpisah Rp{{ number_format($item->item_biaya, 0, ',', '.') }}</span>
                                            @endif
                                        </div>
                                        @if (auth()->user()->can('update', $item))
                                            <a
                                                href="{{ route('laundry.edit', $item) }}"
                                                class="inline-flex min-h-9 items-center rounded-lg border border-line-strong bg-surface px-2.5 text-xs font-bold text-ink hover:bg-sunken"
                                            >
                                                Ubah
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-kartu>
            </div>

            <div class="space-y-4 lg:col-span-5">
                <x-kartu judul="Riwayat Penempatan Kamar" subjudul="Setiap perpindahan tercatat berurutan tanpa memutus kuota">
                    <div class="space-y-3">
                        @foreach ($penghuni->penempatan as $penempatan)
                            <div class="rounded-xl border {{ $penempatan->selesai === null ? 'border-maroon/30 bg-maroon/5' : 'border-line bg-alt' }} px-3.5 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-ink">Kamar {{ $penempatan->kamar?->nomor ?? '-' }}</span>
                                    <span class="rounded-full bg-surface px-2.5 py-0.5 text-xs font-medium text-muted border border-line">
                                        {{ $penempatan->sebab === 'pindah' ? 'Pindah kamar' : 'Masuk awal' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-muted">
                                    {{ $penempatan->mulai->format('d/m/Y') }}
                                    &ndash;
                                    <strong class="text-ink">{{ $penempatan->selesai ? $penempatan->selesai->format('d/m/Y') : 'Sekarang' }}</strong>
                                </p>
                                @if ($penempatan->pembuat)
                                    <p class="mt-0.5 text-[11px] text-subtle">Dicatat oleh {{ $penempatan->pembuat->name }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-xl border border-gold/25 bg-gold-container/70 px-3.5 py-2.5 text-xs text-on-gold">
                        <i class="fas fa-circle-info mr-1" aria-hidden="true"></i>
                        Kuota laundry selalu dijumlahkan per penghuni sepanjang bulan kalender, sehingga pindah kamar tidak pernah mereset kuota ke nol.
                    </div>
                </x-kartu>
            </div>
        </div>
    </div>

    @if ($bolehKelolaPenghuni)
        <x-modal id="modal-ubah-data" judul="Ubah Data Penghuni" lebar="max-w-xl">
            <form
                method="POST"
                action="{{ route('penghuni.update', $penghuni) }}"
                x-data="kontrakSewa({
                    status: @js(old('status', $penghuni->status)),
                    tanggalMasuk: @js(old('tanggal_masuk', $penghuni->tanggal_masuk->toDateString())),
                    jangkaWaktu: @js(old('jangka_waktu_sewa', $penghuni->jangka_waktu_sewa ?? 'tidak_tentu')),
                    tanggalKeluarRencana: @js(old('tanggal_keluar_rencana', $penghuni->tanggal_keluar_rencana?->toDateString() ?? '')),
                    tanggalKeluar: @js(old('tanggal_keluar', $penghuni->tanggal_keluar?->toDateString() ?? now('Asia/Jakarta')->toDateString()))
                })"
                data-track-dirty
                data-draft-key="ubah-penghuni-{{ $penghuni->id }}"
                data-confirm-title="Simpan Perubahan Data & Kontrak?"
                data-confirm-message="Perubahan data profil maupun status kontrak penghuni {{ $penghuni->nama }} akan disimpan dan dicatat ke audit log."
                data-confirm-label="Ya, Simpan"
                data-confirm-variant="maroon"
                class="space-y-4"
            >
                @csrf
                @method('PUT')
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Nama Lengkap" name="nama" id="ubah-nama" :value="$penghuni->nama" :required="true" />
                    <x-field label="Nama Panggilan / Alias" name="alias" id="ubah-alias" :value="$penghuni->alias ?? ''" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Status Kontrak" name="status" id="ubah-status" :required="true">
                        <select
                            id="ubah-status"
                            name="status"
                            x-model="status"
                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        >
                            <option value="booking">Booking</option>
                            <option value="aktif">Aktif</option>
                            <option value="keluar">Selesai (Keluar)</option>
                        </select>
                    </x-field>

                    <x-field
                        label="Tanggal Masuk"
                        name="tanggal_masuk"
                        id="ubah-tanggal-masuk"
                        type="date"
                        x-model="tanggalMasuk"
                        x-on:change="saatUbahTanggalMasuk"
                        :value="old('tanggal_masuk', $penghuni->tanggal_masuk->toDateString())"
                        :required="true"
                    />
                </div>

                <label class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3.5 py-2 text-sm text-ink">
                    <input
                        type="checkbox"
                        name="tanggal_masuk_perkiraan"
                        value="1"
                        @checked((bool) old('tanggal_masuk_perkiraan', $penghuni->tanggal_masuk_perkiraan))
                        class="h-4 w-4 rounded border-line-strong text-maroon focus:ring-maroon"
                    >
                    <span>Tanggal masuk merupakan perkiraan</span>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Jangka Waktu Sewa" name="jangka_waktu_sewa" id="ubah-jangka-waktu" :required="true">
                        <select
                            id="ubah-jangka-waktu"
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
                        id="ubah-keluar-rencana"
                        type="date"
                        x-model="tanggalKeluarRencana"
                        hint="Auto terhitung untuk Bulanan–1 Tahun; boleh dikosongkan / custom jika Tidak Tentu."
                    />
                </div>

                <div x-show="status === 'keluar'" x-cloak class="grid gap-3 rounded-xl border border-danger/25 bg-danger/5 p-3 sm:grid-cols-2">
                    <x-field
                        label="Tanggal Selesai / Keluar"
                        name="tanggal_keluar"
                        id="ubah-tanggal-keluar"
                        type="date"
                        x-model="tanggalKeluar"
                        :value="old('tanggal_keluar', $penghuni->tanggal_keluar?->toDateString() ?? now('Asia/Jakarta')->toDateString())"
                    />
                    <x-field
                        label="Alasan Keluar"
                        name="alasan_keluar"
                        id="ubah-alasan-keluar"
                        :value="$penghuni->alasan_keluar ?? ''"
                        placeholder="Opsional"
                    />
                </div>

                <x-field label="Catatan" name="catatan" id="ubah-catatan" :value="$penghuni->catatan ?? ''" />

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken">
                        Batal
                    </button>
                    <button type="submit" class="min-h-11 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </x-modal>

        <x-modal id="modal-ganti-nomor" judul="Ganti Nomor WhatsApp">
            <form
                method="POST"
                action="{{ route('penghuni.ganti-nomor', $penghuni) }}"
                data-track-dirty
                data-draft-key="ganti-wa-penghuni-{{ $penghuni->id }}"
                data-confirm-title="Simpan Nomor WhatsApp Baru?"
                data-confirm-message="Nomor WhatsApp {{ $penghuni->nama }} akan diperbarui dan dinormalisasi ke format 62."
                data-confirm-label="Ya, Perbarui Nomor"
                data-confirm-variant="maroon"
                class="space-y-4"
            >
                @csrf
                <x-field
                    label="Nomor WhatsApp Baru"
                    name="no_wa"
                    id="ganti-no-wa"
                    type="tel"
                    :value="$penghuni->no_wa ?? ''"
                    placeholder="0812xxxxxxxx"
                    hint="Kosongkan untuk menghapus nomor. Format 08... atau +62... otomatis diubah menjadi 62...."
                />

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken">
                        Batal
                    </button>
                    <button type="submit" class="min-h-11 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah">
                        Simpan Nomor
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($bolehKelolaKontrak && in_array($penghuni->status, ['aktif', 'booking'], true))
        <x-modal id="modal-pindah-kamar" judul="Pindah Kamar: {{ $penghuni->nama }}">
            <div class="mb-4 rounded-xl border border-gold/30 bg-gold-container px-4 py-3 text-xs text-on-gold sm:text-sm">
                <p class="font-bold">Pratinjau Kuota (Tidak Di-reset)</p>
                <p class="mt-0.5">
                    Pemakaian bulan {{ $kuotaBulanIni['label_bulan'] }} saat ini:
                    <strong>{{ $kuotaBulanIni['terpakai_kg'] }} / {{ $kuotaBulanIni['kuota_kg'] }} kg</strong>.
                    Setelah pindah ke kamar baru, perhitungan kuota tetap melanjutkan {{ $kuotaBulanIni['terpakai_kg'] }} kg.
                </p>
            </div>

            @if ($kamarTersedia->isEmpty())
                <p class="text-sm text-muted">Tidak ada kamar aktif lain yang kosong saat ini.</p>
                <div class="mt-4 flex justify-end">
                    <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken">
                        Tutup
                    </button>
                </div>
            @else
                <form
                    method="POST"
                    action="{{ route('penghuni.pindah-kamar', $penghuni) }}"
                    data-track-dirty
                    data-draft-key="pindah-kamar-penghuni-{{ $penghuni->id }}"
                    data-confirm-title="Konfirmasi Pindah Kamar?"
                    data-confirm-message="Kamar lama {{ $kamarTerkini?->nomor }} akan ditutup satu hari sebelum tanggal pindah, dan kuota {{ $kuotaBulanIni['terpakai_kg'] }} kg tetap dilanjutkan."
                    data-confirm-label="Ya, Pindahkan"
                    data-confirm-variant="gold"
                    class="space-y-4"
                >
                    @csrf
                    <x-field label="Kamar Tujuan" name="kamar_tujuan_id" id="pindah-kamar-tujuan" :required="true">
                        <select
                            id="pindah-kamar-tujuan"
                            name="kamar_tujuan_id"
                            required
                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        >
                            <option value="">-- Pilih Kamar Kosong --</option>
                            @foreach ($kamarTersedia as $kamar)
                                <option value="{{ $kamar->id }}" @selected((string) old('kamar_tujuan_id') === (string) $kamar->id)>
                                    Kamar {{ $kamar->nomor }} (Lantai {{ $kamar->lantai }})
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field
                        label="Tanggal Efektif Pindah"
                        name="tanggal_pindah"
                        id="pindah-tanggal"
                        type="date"
                        :value="old('tanggal_pindah', now('Asia/Jakarta')->toDateString())"
                        :required="true"
                        hint="Harus lebih baru dari tanggal mulai penempatan saat ini."
                    />

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken">
                            Batal
                        </button>
                        <button type="submit" class="min-h-11 rounded-xl bg-gold px-5 text-sm font-bold text-white hover:bg-gold-bright">
                            Pindahkan Penghuni
                        </button>
                    </div>
                </form>
            @endif
        </x-modal>

        <x-modal id="modal-keluar" judul="Tandai Penghuni Keluar">
            @if ($kuotaBulanIni['total_tagihan'] > 0)
                <div class="mb-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-xs text-danger sm:text-sm">
                    <strong>Peringatan Tagihan:</strong> Penghuni ini memiliki estimasi tagihan Rp{{ number_format($kuotaBulanIni['total_tagihan'], 0, ',', '.') }} pada bulan {{ $kuotaBulanIni['label_bulan'] }}. Pastikan penagihan sudah diselesaikan.
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('penghuni.keluar', $penghuni) }}"
                data-track-dirty
                data-draft-key="keluar-penghuni-{{ $penghuni->id }}"
                data-confirm-title="Konfirmasi Penghuni Keluar?"
                data-confirm-message="{{ $penghuni->nama }} akan berstatus keluar dan kamar {{ $kamarTerkini?->nomor }} langsung menjadi kosong."
                data-confirm-label="Ya, Tandai Keluar"
                data-confirm-variant="danger"
                class="space-y-4"
            >
                @csrf
                <x-field
                    label="Tanggal Keluar"
                    name="tanggal_keluar"
                    id="keluar-tanggal"
                    type="date"
                    :value="old('tanggal_keluar', now('Asia/Jakarta')->toDateString())"
                    :required="true"
                />

                <x-field
                    label="Alasan Keluar"
                    name="alasan_keluar"
                    id="keluar-alasan"
                    placeholder="Opsional, mis. Selesai kontrak / pindah kerja"
                />

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken">
                        Batal
                    </button>
                    <button type="submit" class="min-h-11 rounded-xl bg-danger px-5 text-sm font-bold text-white hover:bg-danger/90">
                        Simpan Status Keluar
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($bolehSesuaikanKuota && $kuotaBulanIni['mode_kuota'] !== 'tanpa_kuota')
        <x-modal id="modal-penyesuaian-kuota" judul="Sesuaikan Tagihan Lewat Kuota ({{ $kuotaBulanIni['label_bulan'] }})">
            <div class="mb-4 rounded-xl border border-line bg-alt px-4 py-3 text-xs text-muted sm:text-sm">
                Hitungan otomatis saat ini: <strong class="text-ink">Rp{{ number_format($kuotaBulanIni['tagihan_otomatis'], 0, ',', '.') }}</strong>
                (kelebihan {{ $kuotaBulanIni['kelebihan_kg'] }} kg).
            </div>

            <form
                method="POST"
                action="{{ route('penghuni.penyesuaian-kuota', $penghuni) }}"
                data-track-dirty
                data-draft-key="penyesuaian-kuota-{{ $penghuni->id }}-{{ $kuotaBulanIni['bulan'] }}"
                data-confirm-title="Simpan Penyesuaian Tagihan Kuota?"
                data-confirm-message="Nominal tagihan lewat kuota bulan {{ $kuotaBulanIni['label_bulan'] }} untuk {{ $penghuni->nama }} akan memakai nilai manual Anda."
                data-confirm-label="Ya, Simpan Nominal"
                data-confirm-variant="maroon"
                class="space-y-4"
            >
                @csrf
                <input type="hidden" name="bulan" value="{{ $kuotaBulanIni['bulan'] }}">

                <x-field
                    label="Nominal Manual (Rupiah)"
                    name="nominal"
                    id="penyesuaian-nominal"
                    type="number"
                    min="0"
                    step="100"
                    :value="old('nominal', $kuotaBulanIni['nominal_penyesuaian'] ?? $kuotaBulanIni['tagihan_otomatis'])"
                    :required="true"
                />

                <x-field
                    label="Catatan Alasan Penyesuaian"
                    name="catatan"
                    id="penyesuaian-catatan"
                    :value="old('catatan', $kuotaBulanIni['catatan_penyesuaian'] ?? '')"
                    placeholder="Mis. Pembulatan khusus / kesepakatan penghuni"
                />

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken">
                        Batal
                    </button>
                    <button type="submit" class="min-h-11 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah">
                        Simpan Penyesuaian
                    </button>
                </div>
            </form>

            @if ($kuotaBulanIni['nominal_penyesuaian'] !== null)
                <form
                    method="POST"
                    action="{{ route('penghuni.penyesuaian-kuota', $penghuni) }}"
                    data-confirm-title="Hapus Penyesuaian Manual?"
                    data-confirm-message="Tagihan lewat kuota bulan {{ $kuotaBulanIni['label_bulan'] }} akan kembali mengikuti hitungan otomatis (Rp{{ number_format($kuotaBulanIni['tagihan_otomatis'], 0, ',', '.') }})."
                    data-confirm-label="Ya, Kembalikan Otomatis"
                    data-confirm-variant="gold"
                    class="mt-3 border-t border-line pt-3"
                >
                    @csrf
                    <input type="hidden" name="bulan" value="{{ $kuotaBulanIni['bulan'] }}">
                    <input type="hidden" name="reset" value="1">
                    <button type="submit" class="min-h-11 w-full rounded-xl border border-gold/40 bg-gold-container px-4 text-xs font-bold text-on-gold hover:bg-gold/25">
                        Kembalikan ke Hitungan Otomatis (Rp{{ number_format($kuotaBulanIni['tagihan_otomatis'], 0, ',', '.') }})
                    </button>
                </form>
            @endif
        </x-modal>
    @endif
</x-app-layout>
