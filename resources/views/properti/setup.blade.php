<x-app-layout :judul="'Setup ' . $properti->nama" subjudul="Lengkapi data, kamar, aturan laundry, & staf — simpan sekaligus di bawah">
    <x-slot:aksiHeader>
        <x-lencana :status="$properti->status" />
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

        {{-- Ringkasan Progres Kesiapan --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-ringkasan-tile
                label="1. Data Properti"
                :nilai="$properti->nama"
                :keterangan="$properti->nama_properti_laporan"
                aksen="maroon"
            />
            <x-ringkasan-tile
                label="2. Kamar Aktif"
                :nilai="$properti->kamar->where('aktif', true)->count() . ' kamar'"
                :keterangan="$siapKamar ? 'Siap' : 'Minimal 1 kamar'"
                :aksen="$siapKamar ? 'ok' : 'danger'"
            />
            <x-ringkasan-tile
                label="3. Aturan Laundry"
                :nilai="$siapAturan ? 'Tersimpan' : 'Bawaan'"
                :keterangan="'Rp' . number_format((int) ($aturanSaatIni['tarif_per_kg'] ?? 8000), 0, ',', '.') . '/kg · Kuota ' . ($aturanSaatIni['kuota_kg'] ?? 20) . ' kg'"
                :aksen="$siapAturan ? 'ok' : 'gold'"
            />
            <x-ringkasan-tile
                label="4. Admin Aktif"
                :nilai="$properti->adminAktif?->name ?? 'Belum ada'"
                :keterangan="$siapAdmin ? 'Siap' : 'Wajib 1 admin aktif'"
                :aksen="$siapAdmin ? 'ok' : 'danger'"
            />
        </div>

        {{-- Langkah 1: Data Properti (Terhubung ke form-setup-semua) --}}
        <x-kartu judul="Langkah 1 · Data Properti" subjudul="Informasi dasar dan judul yang tampil di laporan PDF/cetak">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field
                    label="Nama Properti"
                    name="nama"
                    :value="$properti->nama"
                    :required="true"
                    form="form-setup-semua"
                />
                <x-field
                    label="Nama Properti di Laporan"
                    name="nama_properti_laporan"
                    :value="$properti->nama_properti_laporan"
                    :required="true"
                    form="form-setup-semua"
                />
                <div class="sm:col-span-2">
                    <x-field
                        label="Alamat"
                        name="alamat"
                        :value="$properti->alamat ?? ''"
                        placeholder="Alamat lengkap properti (opsional)"
                        form="form-setup-semua"
                    />
                </div>
                <div class="sm:col-span-2">
                    <x-field
                        label="Status Operasional Properti"
                        name="status"
                        id="setup-status"
                        hint="Jika diubah ke Draf atau Nonaktif (Arsip), staf (admin & penjaga) tidak bisa login, sedangkan data laundry, kontrak, penghuni, dan kamar tetap aman."
                    >
                        <select
                            id="setup-status"
                            name="status"
                            form="form-setup-semua"
                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none"
                        >
                            <option value="aktif" @selected(old('status', $properti->status) === 'aktif')>Aktif — Staf dapat login dan operasional berjalan</option>
                            <option value="draf" @selected(old('status', $properti->status) === 'draf')>Draf — Masih tahap setup, akses staf dikunci</option>
                            <option value="arsip" @selected(old('status', $properti->status) === 'arsip')>Nonaktif / Arsip — Operasional dihentikan, semua data tetap tersimpan</option>
                        </select>
                    </x-field>
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button
                        type="submit"
                        form="form-setup-semua"
                        class="min-h-11 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken"
                    >
                        Simpan Data Properti
                    </button>
                </div>
            </div>
        </x-kartu>

        {{-- Langkah 2: Generator Kamar per Lantai --}}
        <x-kartu judul="Langkah 2 · Generator Kamar per Lantai" subjudul="Tambahkan rentang nomor kamar sekaligus per lantai secara idempoten">
            <div x-data="wizardKamar" class="space-y-4">
                <form method="POST" action="{{ route('properti.setup.kamar', $properti) }}" class="space-y-3">
                    @csrf
                    <template x-for="(item, idx) in baris" :key="idx">
                        <div class="grid grid-cols-12 items-end gap-2 rounded-xl border border-line bg-alt p-3">
                            <div class="col-span-3">
                                <label :for="'lantai-' + idx" class="block text-xs font-medium text-muted">Lantai</label>
                                <input
                                    :id="'lantai-' + idx"
                                    :name="'lantai[' + idx + '][lantai]'"
                                    x-model.number="item.lantai"
                                    type="number"
                                    min="1"
                                    max="99"
                                    required
                                    class="mt-1 block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink"
                                >
                            </div>
                            <div class="col-span-4 sm:col-span-3">
                                <label :for="'dari-' + idx" class="block text-xs font-medium text-muted">Dari Nomor</label>
                                <input
                                    :id="'dari-' + idx"
                                    :name="'lantai[' + idx + '][dari]'"
                                    x-model.number="item.dari"
                                    type="number"
                                    min="1"
                                    required
                                    class="mt-1 block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink"
                                >
                            </div>
                            <div class="col-span-5 sm:col-span-4">
                                <label :for="'sampai-' + idx" class="block text-xs font-medium text-muted">Sampai Nomor</label>
                                <input
                                    :id="'sampai-' + idx"
                                    :name="'lantai[' + idx + '][sampai]'"
                                    x-model.number="item.sampai"
                                    type="number"
                                    min="1"
                                    required
                                    class="mt-1 block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink"
                                >
                            </div>
                            <div class="col-span-12 sm:col-span-2 flex justify-end">
                                <button
                                    type="button"
                                    x-show="baris.length > 1"
                                    x-on:click="hapus(idx)"
                                    class="min-h-11 w-full rounded-xl border border-danger/30 px-3 text-xs font-bold text-danger hover:bg-danger/10"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button
                            type="button"
                            x-on:click="tambah"
                            class="min-h-11 rounded-xl border border-line-strong bg-surface px-3.5 text-xs font-bold text-ink hover:bg-sunken"
                        >
                            + Tambah Baris Lantai
                        </button>
                        <button
                            type="submit"
                            class="min-h-11 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                        >
                            Generate Kamar
                        </button>
                    </div>
                </form>

                @if ($kamarPerLantai->isNotEmpty())
                    <div class="space-y-2 border-t border-line pt-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-muted">Kamar Terdaftar</div>
                        @foreach ($kamarPerLantai as $lantai => $daftarKamar)
                            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                <span class="mr-1 font-bold text-ink">Lantai {{ $lantai }}:</span>
                                @foreach ($daftarKamar as $kamar)
                                    <span class="rounded-lg border border-line-strong bg-alt px-2 py-0.5 font-medium text-ink">
                                        {{ $kamar->nomor }}
                                    </span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-kartu>

        {{-- Form Terpadu Setup: Langkah 1 (via atribut form), Langkah 3, Langkah 4, & Bar Simpan Semua --}}
        <form
            id="form-setup-semua"
            method="POST"
            action="{{ route('properti.setup.simpan-semua', $properti) }}"
            data-track-dirty
            data-draft-key="setup-properti-{{ $properti->id }}"
            data-confirm-title="Simpan Semua Perubahan Setup?"
            data-confirm-message="Data properti, aturan laundry, serta akun staf baru (apabila kolom karyawan diisi) akan disimpan sekaligus."
            data-confirm-label="Ya, Simpan Semua"
            data-confirm-variant="maroon"
            class="space-y-4"
        >
            @csrf

            {{-- Langkah 3: Aturan Laundry --}}
            <x-kartu judul="Langkah 3 · Aturan Laundry Properti" subjudul="Disalin dari nilai bawaan sistem dan dapat disesuaikan per properti">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Tarif per Kg (Rp)" name="tarif_per_kg" type="number" min="100" max="1000000" :value="$aturanSaatIni['tarif_per_kg'] ?? 8000" :required="true" />
                    <x-field label="Minimum Berat (Kg)" name="min_kg" type="number" min="0" max="50" step="0.1" :value="$aturanSaatIni['min_kg'] ?? 2" :required="true" />
                    <x-field label="Kuota Bulanan (Kg)" name="kuota_kg" type="number" min="0" max="500" step="0.1" :value="$aturanSaatIni['kuota_kg'] ?? 20" :required="true" />
                    <x-field label="Ambang Mendekati (%)" name="ambang_mendekati_persen" type="number" min="1" max="100" :value="$aturanSaatIni['ambang_mendekati_persen'] ?? 75" :required="true" />
                    <x-field label="Toleransi Lewat Kuota (Kg)" name="toleransi_kg" type="number" min="0" max="50" step="0.1" :value="$aturanSaatIni['toleransi_kg'] ?? 1" :required="true" />
                    <x-field label="Pembulatan Tagihan (Rp)" name="pembulatan" type="number" min="1" max="100000" :value="$aturanSaatIni['pembulatan'] ?? 1000" :required="true" />

                    <x-field label="Mode Kuota" name="mode_kuota" :required="true">
                        <select id="mode_kuota" name="mode_kuota" class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink">
                            <option value="penghuni" @selected(old('mode_kuota', $aturanSaatIni['mode_kuota'] ?? 'penghuni') === 'penghuni')>Per Penghuni</option>
                            <option value="tanpa_kuota" @selected(old('mode_kuota', $aturanSaatIni['mode_kuota'] ?? '') === 'tanpa_kuota')>Tanpa Kuota</option>
                        </select>
                    </x-field>

                    <x-field label="Batas Jam Edit Penjaga" name="jam_edit_penjaga" type="number" min="1" max="168" :value="$aturanSaatIni['jam_edit_penjaga'] ?? 24" :required="true" />

                    <div class="flex items-end pb-1">
                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-ink">
                            <input
                                type="checkbox"
                                name="penjaga_kelola_kontrak"
                                value="1"
                                @checked((bool) old('penjaga_kelola_kontrak', ($aturanSaatIni['penjaga_kelola_kontrak'] ?? '0') === '1'))
                                class="h-4 w-4 rounded border-line-strong text-maroon focus:ring-maroon"
                            >
                            <span>Izinkan penjaga kelola kontrak</span>
                        </label>
                    </div>

                    <div class="sm:col-span-3 flex justify-end">
                        <button type="submit" class="min-h-11 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken">
                            Simpan Aturan Properti
                        </button>
                    </div>
                </div>
            </x-kartu>

            {{-- Langkah 4: Tunjuk Admin & Penjaga + Aktivasi --}}
            <x-kartu judul="Langkah 4 · Penugasan Staf & Aktivasi" subjudul="Tetapkan 1 admin aktif dan penjaga untuk properti ini (kosongkan form karyawan bila tidak menambah staf baru)">
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-line bg-alt p-3.5">
                            <div class="text-xs font-medium text-muted">Admin Properti Aktif</div>
                            <div class="mt-1 text-sm font-bold text-ink">
                                {{ $properti->adminAktif?->name ?? 'Belum ditetapkan' }}
                            </div>
                            @if ($properti->adminAktif)
                                <div class="text-xs text-muted font-mono">{{ $properti->adminAktif->username }}</div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-line bg-alt p-3.5">
                            <div class="text-xs font-medium text-muted">Daftar Penjaga ({{ $properti->penjaga->count() }})</div>
                            @if ($properti->penjaga->isEmpty())
                                <div class="mt-1 text-sm text-muted">Belum ada penjaga terdaftar</div>
                            @else
                                <ul class="mt-1 space-y-0.5 text-xs text-ink">
                                    @foreach ($properti->penjaga as $p)
                                        <li>{{ $p->name }} (<span class="font-mono">{{ $p->username }}</span>)</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-3 rounded-xl border border-line bg-alt p-4 sm:grid-cols-2">
                        <x-field label="Nama Karyawan" name="name" id="wizard_name" maxlength="120" placeholder="Nama admin atau penjaga (opsional)" />
                        <x-field label="Username" name="username" id="wizard_username" minlength="3" maxlength="50" placeholder="Bebas tanpa spasi, boleh @#$ dll." />
                        <x-field label="Password Awal" name="password" id="wizard_password" type="password" minlength="8" placeholder="Minimal 8 karakter (opsional)" />
                        <x-field label="Peran" name="role" id="wizard_role">
                            <select id="wizard_role" name="role" class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink">
                                @if (! $siapAdmin)
                                    <option value="admin" @selected(old('role') === 'admin')>Admin Properti</option>
                                @endif
                                <option value="penjaga" @selected(old('role') === 'penjaga')>Penjaga</option>
                            </select>
                        </x-field>

                        <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-2 pt-1">
                            <a href="{{ route('pengguna.index') }}" class="text-xs font-bold text-maroon hover:underline">
                                Kelola lengkap di menu Pengguna →
                            </a>
                            <button type="submit" class="min-h-11 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken">
                                + Tambah Staf & Simpan
                            </button>
                        </div>
                    </div>
                </div>
            </x-kartu>

            {{-- Panel Bawah: Tombol Utama Simpan Semua Setup --}}
            <x-kartu class="border-maroon/25 bg-surface">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-xs text-muted sm:text-sm">
                        @if ($properti->status === 'aktif')
                            <i class="fas fa-circle-check mr-1 text-ok" aria-hidden="true"></i>
                            Properti ini berstatus <strong class="text-ok">Aktif</strong> dan siap digunakan staf. Untuk menghentikan akses login staf sementara tanpa menghapus data, ubah <strong>Status Operasional</strong> di Langkah 1 menjadi <strong>Draf</strong> atau <strong>Nonaktif / Arsip</strong>.
                        @elseif ($siapKamar && $siapAdmin)
                            <i class="fas fa-circle-info mr-1 text-gold" aria-hidden="true"></i>
                            Seluruh syarat minimum terpenuhi. Anda dapat menyimpan sekaligus mengaktifkan properti sekarang.
                        @else
                            <i class="fas fa-circle-exclamation mr-1 text-warn" aria-hidden="true"></i>
                            Lengkapi minimal <strong>1 kamar aktif</strong> dan <strong>1 admin properti aktif</strong> untuk mengaktifkan properti.
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <a
                            href="{{ route('properti.index') }}"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line-strong bg-surface px-4 text-sm font-medium text-ink hover:bg-sunken"
                        >
                            Kembali ke Properti
                        </a>

                        @if ($properti->status !== 'aktif')
                            <button
                                type="submit"
                                name="aktifkan_sekarang"
                                value="1"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gold px-5 text-sm font-bold text-white hover:bg-gold-bright"
                            >
                                <i class="fas fa-bolt text-xs" aria-hidden="true"></i>
                                <span>Simpan Semua & Aktifkan</span>
                            </button>
                        @endif

                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-maroon px-6 text-sm font-bold text-white shadow-xs hover:bg-maroon-cerah"
                        >
                            <i class="fas fa-floppy-disk text-xs" aria-hidden="true"></i>
                            <span>Simpan Semua Setup</span>
                        </button>
                    </div>
                </div>
            </x-kartu>
        </form>
    </div>
</x-app-layout>
