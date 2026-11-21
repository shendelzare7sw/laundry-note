<x-app-layout judul="Pengaturan Properti" :subjudul="$propertiAktif->nama">
    @php
        $daftarTab = [
            'aturan' => ['label' => 'Tarif & Kuota', 'ikon' => 'fa-scale-balanced'],
            'izin' => ['label' => 'Izin Penjaga', 'ikon' => 'fa-user-shield'],
            'whatsapp' => ['label' => 'Template WhatsApp', 'ikon' => 'fa-brands fa-whatsapp'],
        ];
        $variabelWa = [
            '{nama}', '{kamar}', '{gedung}', '{periode}', '{rincian_tagihan}',
            '{rincian}', '{kuota_terpakai}', '{kuota}', '{sisa}', '{lewat_kg}',
            '{tagihan_lewat}', '{terpisah}', '{total}',
        ];
        $labelKunciAturan = [
            'nama_properti' => 'Nama Properti Laporan',
            'tarif_per_kg' => 'Tarif per Kg (Rp)',
            'min_kg' => 'Minimum Berat (kg)',
            'kuota_kg' => 'Kuota Gratis Bulanan (kg)',
            'ambang_mendekati_persen' => 'Ambang Mendekati (%)',
            'toleransi_kg' => 'Toleransi Kelebihan (kg)',
            'pembulatan' => 'Pembulatan Tagihan (Rp)',
            'mode_kuota' => 'Mode Kuota',
            'kuota_prorata_bulan_masuk' => 'Prorata Bulan Masuk',
        ];
    @endphp

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

        {{-- Navigasi Tab (Selalu 3 sejajar rapi di mobile & zoom manapun) --}}
        <nav aria-label="Kategori pengaturan properti" class="grid grid-cols-3 gap-1.5 sm:inline-flex sm:gap-2">
            @foreach ($daftarTab as $kode => $info)
                @php $aktif = $tab === $kode; @endphp
                <a
                    href="{{ route('pengaturan.index', ['tab' => $kode]) }}"
                    class="inline-flex min-h-11 min-w-0 items-center justify-center gap-1.5 rounded-full border px-2 text-center text-xs font-bold transition sm:px-4 sm:text-sm {{ $aktif ? 'border-maroon bg-maroon text-white' : 'border-line-strong bg-surface text-ink hover:bg-sunken' }}"
                >
                    <i class="{{ str_contains($info['ikon'], ' ') ? $info['ikon'] : 'fas ' . $info['ikon'] }} shrink-0 text-xs" aria-hidden="true"></i>
                    <span class="truncate">{{ $info['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- TAB 1: ATURAN TARIF & KUOTA BERVERSI --}}
        @if ($tab === 'aturan')
            <x-kartu
                judul="Aturan Tarif & Kuota Berversi"
                subjudul="Perubahan aturan berlaku mulai tanggal yang dipilih dan tidak mengubah perhitungan catatan bulan sebelumnya."
            >
                <form
                    method="POST"
                    action="{{ route('pengaturan.aturan') }}"
                    data-track-dirty
                    data-draft-key="pengaturan-aturan-{{ $propertiAktif->id }}"
                    data-confirm-title="Simpan Versi Aturan Baru?"
                    data-confirm-message="Aturan tarif dan kuota ini akan berlaku mulai tanggal yang ditentukan dan dicatat ke audit log."
                    data-confirm-label="Ya, Simpan Aturan"
                    data-confirm-variant="maroon"
                    class="space-y-4"
                >
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field
                            label="Berlaku Mulai Tanggal"
                            name="berlaku_mulai"
                            id="aturan-berlaku-mulai"
                            type="date"
                            :value="old('berlaku_mulai', $hariIni)"
                            :required="true"
                            hint="Pilih tanggal 1 bulan depan jika ingin mengubah tarif tanpa menyentuh bulan ini."
                        />

                        <x-field
                            label="Nama Properti (Laporan & WA)"
                            name="nama_properti"
                            id="aturan-nama-properti"
                            :value="old('nama_properti', $nilaiAktif['nama_properti'] ?? $propertiAktif->nama_properti_laporan)"
                            :required="true"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-field
                            label="Tarif per Kg (Rp)"
                            name="tarif_per_kg"
                            id="aturan-tarif"
                            type="number"
                            min="100"
                            step="100"
                            :value="old('tarif_per_kg', $nilaiAktif['tarif_per_kg'] ?? '8000')"
                            :required="true"
                        />

                        <x-field
                            label="Minimum Berat per Catatan (kg)"
                            name="min_kg"
                            id="aturan-min-kg"
                            type="text"
                            inputmode="decimal"
                            :value="old('min_kg', $nilaiAktif['min_kg'] ?? '2')"
                            :required="true"
                        />

                        <x-field
                            label="Kuota Gratis Bulanan (kg)"
                            name="kuota_kg"
                            id="aturan-kuota-kg"
                            type="text"
                            inputmode="decimal"
                            :value="old('kuota_kg', $nilaiAktif['kuota_kg'] ?? '20')"
                            :required="true"
                        />

                        <x-field
                            label="Ambang Mendekati Kuota (%)"
                            name="ambang_mendekati_persen"
                            id="aturan-ambang"
                            type="number"
                            min="1"
                            max="100"
                            :value="old('ambang_mendekati_persen', $nilaiAktif['ambang_mendekati_persen'] ?? '75')"
                            :required="true"
                        />

                        <x-field
                            label="Toleransi Kelebihan (kg)"
                            name="toleransi_kg"
                            id="aturan-toleransi"
                            type="text"
                            inputmode="decimal"
                            :value="old('toleransi_kg', $nilaiAktif['toleransi_kg'] ?? '1')"
                            :required="true"
                            hint="Tagihan lewat kuota dikenakan bila kelebihan ≥ nilai ini."
                        />

                        <x-field label="Pembulatan Tagihan (Rp)" name="pembulatan" id="aturan-pembulatan" :required="true">
                            @php $pembulatanAktif = (string) old('pembulatan', $nilaiAktif['pembulatan'] ?? '1000'); @endphp
                            <select
                                id="aturan-pembulatan"
                                name="pembulatan"
                                class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                            >
                                @foreach ([1 => 'Tanpa pembulatan (Rp1)', 100 => 'Rp100', 500 => 'Rp500', 1000 => 'Rp1.000', 5000 => 'Rp5.000'] as $val => $lbl)
                                    <option value="{{ $val }}" @selected($pembulatanAktif === (string) $val)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </x-field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Mode Kuota" name="mode_kuota" id="aturan-mode-kuota" :required="true">
                            @php $modeKuotaAktif = (string) old('mode_kuota', $nilaiAktif['mode_kuota'] ?? 'penghuni'); @endphp
                            <select
                                id="aturan-mode-kuota"
                                name="mode_kuota"
                                class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                            >
                                <option value="penghuni" @selected($modeKuotaAktif === 'penghuni')>Per Penghuni (kuota bulanan aktif)</option>
                                <option value="tanpa_kuota" @selected($modeKuotaAktif === 'tanpa_kuota')>Tanpa Kuota (hanya pencatatan)</option>
                            </select>
                        </x-field>

                        <div class="flex items-end">
                            <label class="flex min-h-11 w-full cursor-pointer items-center gap-2.5 rounded-xl border border-line-strong bg-alt px-3.5 py-2 text-sm text-ink">
                                <input
                                    type="checkbox"
                                    name="kuota_prorata_bulan_masuk"
                                    value="1"
                                    @checked((string) old('kuota_prorata_bulan_masuk', $nilaiAktif['kuota_prorata_bulan_masuk'] ?? '0') === '1')
                                    class="h-4 w-4 rounded border-line-strong text-maroon focus:ring-maroon"
                                >
                                <span>Aktifkan kuota prorata pada bulan pertama masuk</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah"
                        >
                            <i class="fas fa-floppy-disk text-xs" aria-hidden="true"></i>
                            <span>Simpan Versi Aturan</span>
                        </button>
                    </div>
                </form>
            </x-kartu>

            {{-- Riwayat Versi Aturan --}}
            <x-kartu judul="Riwayat Versi Aturan Properti" subjudul="Menampilkan seluruh versi aturan tarif dan kuota yang pernah disimpan.">
                @if ($riwayatVersi->isEmpty())
                    <p class="text-sm text-muted">Belum ada riwayat aturan khusus untuk properti ini (menggunakan aturan bawaan sistem).</p>
                @else
                    <div class="space-y-2">
                        @foreach ($riwayatVersi as $baris)
                            <div
                                id="m-{{ $loop->iteration }}"
                                class="scroll-mt-20 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-alt px-3.5 py-2.5 text-xs sm:text-sm"
                            >
                                <div class="min-w-0">
                                    <span class="font-bold text-ink">{{ $labelKunciAturan[$baris->kunci] ?? $baris->kunci }}</span>
                                    <span class="mx-1.5 text-subtle">→</span>
                                    <span class="font-bold text-maroon">{{ $baris->nilai }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                                    <span class="rounded-full bg-surface px-2.5 py-0.5 font-medium text-ink border border-line">
                                        Berlaku {{ $baris->berlaku_mulai->format('d/m/Y') }}
                                    </span>
                                    @if ($baris->pengubah)
                                        <span>oleh {{ $baris->pengubah->name }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <x-muat-lagi :items="$riwayatVersi" :langkah="15" :satu-daftar="true" />
                    </div>
                @endif
            </x-kartu>
        @endif

        {{-- TAB 2: IZIN & BATAS OPERASIONAL PENJAGA --}}
        @if ($tab === 'izin')
            <x-kartu
                judul="Izin & Batas Operasional Penjaga"
                subjudul="Atur hak akses penjaga di properti ini. Perubahan langsung berlaku dan seluruh aksi tetap tercatat di audit log."
            >
                <form
                    method="POST"
                    action="{{ route('pengaturan.izin-penjaga') }}"
                    data-track-dirty
                    data-draft-key="pengaturan-izin-{{ $propertiAktif->id }}"
                    data-confirm-title="Simpan Pengaturan Izin Penjaga?"
                    data-confirm-message="Perubahan izin dan batas jam edit ini akan langsung berlaku untuk seluruh akun penjaga di {{ $propertiAktif->nama }}."
                    data-confirm-label="Ya, Simpan Izin"
                    data-confirm-variant="maroon"
                    class="space-y-4"
                >
                    @csrf
                    @method('PUT')

                    <div class="max-w-sm">
                        <x-field
                            label="Batas Jam Edit Catatan Penjaga (jam)"
                            name="jam_edit_penjaga"
                            id="izin-jam-edit"
                            type="number"
                            min="1"
                            max="720"
                            :value="old('jam_edit_penjaga', $nilaiAktif['jam_edit_penjaga'] ?? '24')"
                            placeholder="Kosongkan jika tanpa batas"
                            hint="Kosongkan jika tanpa batas waktu edit. Contoh: 24 jam (1 hari) atau 168 jam (7 hari) sejak catatan dibuat."
                        />
                    </div>

                    <div class="space-y-3">
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line-strong bg-alt p-4 transition hover:bg-sunken/60">
                            <input
                                type="checkbox"
                                name="penjaga_ubah_catatan_lain"
                                value="1"
                                @checked((string) old('penjaga_ubah_catatan_lain', $nilaiAktif['penjaga_ubah_catatan_lain'] ?? '0') === '1')
                                class="mt-1 h-5 w-5 shrink-0 rounded border-line-strong text-maroon focus:ring-maroon"
                            >
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink">Izinkan penjaga ubah & hapus catatan laundry pengguna lain</div>
                                <p class="mt-0.5 text-xs text-muted">
                                    Jika diaktifkan, penjaga dapat mengedit atau menghapus catatan laundry yang dicatat oleh Admin maupun penjaga shift lain (tetap dalam batas jam edit di atas). Jika nonaktif, penjaga hanya bisa mengubah catatan yang dibuat oleh dirinya sendiri.
                                </p>
                            </div>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line-strong bg-alt p-4 transition hover:bg-sunken/60">
                            <input
                                type="checkbox"
                                name="penjaga_kelola_kontrak"
                                value="1"
                                @checked((string) old('penjaga_kelola_kontrak', $nilaiAktif['penjaga_kelola_kontrak'] ?? '0') === '1')
                                class="mt-1 h-5 w-5 shrink-0 rounded border-line-strong text-maroon focus:ring-maroon"
                            >
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-ink">Izinkan penjaga kelola kontrak kamar (Saklar K6)</div>
                                <p class="mt-0.5 text-xs text-muted">
                                    Jika diaktifkan, seluruh penjaga di properti ini berhak mencatat penghuni masuk, pindah kamar, keluar, dan batalkan keluar.
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah"
                        >
                            <i class="fas fa-floppy-disk text-xs" aria-hidden="true"></i>
                            <span>Simpan Izin Penjaga</span>
                        </button>
                    </div>
                </form>
            </x-kartu>
        @endif

        {{-- TAB 3: EDITOR TEMPLATE WHATSAPP --}}
        @if ($tab === 'whatsapp')
            <div
                x-data="editorTemplateWa({
                    tagihan: @js(old('template_wa_tagihan', $nilaiAktif['template_wa_tagihan'] ?? '')),
                    peringatan: @js(old('template_wa_peringatan', $nilaiAktif['template_wa_peringatan'] ?? '')),
                    rekap: @js(old('template_wa_rekap', $nilaiAktif['template_wa_rekap'] ?? '')),
                    gedung: @js($nilaiAktif['nama_properti'] ?? $propertiAktif->nama_properti_laporan)
                })"
                class="space-y-4"
            >
                <x-kartu
                    judul="Variabel Template WhatsApp"
                    subjudul="Klik variabel di bawah untuk menyisipkan otomatis ke editor yang sedang aktif. Variabel kosong akan dibersihkan otomatis."
                >
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($variabelWa as $var)
                            <button
                                type="button"
                                x-on:click="sisipkan('{{ $var }}')"
                                class="inline-flex min-h-9 items-center rounded-lg border border-line-strong bg-alt px-2.5 font-mono text-xs font-bold text-maroon hover:bg-maroon/10"
                            >
                                {{ $var }}
                            </button>
                        @endforeach
                    </div>
                </x-kartu>

                <form
                    method="POST"
                    action="{{ route('pengaturan.template-wa') }}"
                    data-track-dirty
                    data-draft-key="pengaturan-wa-{{ $propertiAktif->id }}"
                    data-confirm-title="Simpan Template WhatsApp?"
                    data-confirm-message="Template pesan WhatsApp baru akan langsung digunakan pada tombol kirim tagihan, peringatan kuota, dan rekap gedung."
                    data-confirm-label="Ya, Simpan Template"
                    data-confirm-variant="maroon"
                    class="space-y-4"
                >
                    @csrf
                    @method('PUT')

                    @foreach ([
                        'tagihan' => ['kunci' => 'template_wa_tagihan', 'judul' => '1. Template Tagihan per Penghuni', 'rows' => 10],
                        'peringatan' => ['kunci' => 'template_wa_peringatan', 'judul' => '2. Template Peringatan Kuota', 'rows' => 6],
                        'rekap' => ['kunci' => 'template_wa_rekap', 'judul' => '3. Template Rekap Seluruh Gedung', 'rows' => 4],
                    ] as $kodeEd => $cfg)
                        <x-kartu :judul="$cfg['judul']">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label for="wa-{{ $kodeEd }}" class="block text-xs font-bold text-muted">Isi Template</label>
                                    <textarea
                                        id="wa-{{ $kodeEd }}"
                                        name="{{ $cfg['kunci'] }}"
                                        rows="{{ $cfg['rows'] }}"
                                        x-model="{{ $kodeEd }}"
                                        x-on:focus="aktif = '{{ $kodeEd }}'"
                                        required
                                        class="w-full rounded-xl border border-line-strong bg-surface p-3 font-mono text-xs leading-relaxed text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                                    ></textarea>
                                </div>

                                <div class="space-y-1.5">
                                    <p class="text-xs font-bold text-muted">Pratinjau Contoh Pesan</p>
                                    <pre
                                        x-text="pratinjau('{{ $kodeEd }}')"
                                        class="min-h-28 whitespace-pre-wrap rounded-xl border border-ok/25 bg-ok/5 p-3 font-sans text-xs leading-relaxed text-ink"
                                    ></pre>
                                </div>
                            </div>
                        </x-kartu>
                    @endforeach

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button
                            type="submit"
                            form="form-reset-wa-semua"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken"
                        >
                            <i class="fas fa-rotate-left text-xs" aria-hidden="true"></i>
                            <span>Reset ke Template Awal</span>
                        </button>

                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah"
                        >
                            <i class="fas fa-floppy-disk text-xs" aria-hidden="true"></i>
                            <span>Simpan Template WhatsApp</span>
                        </button>
                    </div>
                </form>

                <form
                    id="form-reset-wa-semua"
                    method="POST"
                    action="{{ route('pengaturan.template-wa.reset') }}"
                    data-confirm-title="Reset Semua Template WhatsApp?"
                    data-confirm-message="Seluruh template tagihan, peringatan kuota, dan rekap gedung akan dikembalikan ke format bawaan awal."
                    data-confirm-label="Ya, Reset Bawaan"
                    data-confirm-variant="danger"
                >
                    @csrf
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
