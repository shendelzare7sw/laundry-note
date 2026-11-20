<x-app-layout :judul="'Setup ' . $properti->nama">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-ink">Wizard Setup Properti: {{ $properti->nama }}</h1>
                <p class="text-sm text-muted">Lengkapi kamar, aturan laundry, dan penugasan staf sebelum properti diaktifkan</p>
            </div>
            <x-lencana :status="$properti->status" />
        </div>

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

        {{-- Langkah 1: Data Properti --}}
        <x-kartu judul="Langkah 1 · Data Properti" subjudul="Informasi dasar dan judul yang tampil di laporan PDF/cetak">
            <form method="POST" action="{{ route('properti.update', $properti) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-field label="Nama Properti" name="nama" :value="$properti->nama" :required="true" />
                <x-field label="Nama Properti di Laporan" name="nama_properti_laporan" :value="$properti->nama_properti_laporan" :required="true" />
                <div class="sm:col-span-2">
                    <x-field label="Alamat" name="alamat" :value="$properti->alamat ?? ''" placeholder="Alamat lengkap properti (opsional)" />
                </div>
                <input type="hidden" name="status" value="{{ $properti->status }}">
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="min-h-11 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken">
                        Simpan Data Properti
                    </button>
                </div>
            </form>
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

        {{-- Langkah 3: Aturan Laundry --}}
        <x-kartu judul="Langkah 3 · Aturan Laundry Properti" subjudul="Disalin dari nilai bawaan sistem dan dapat disesuaikan per properti">
            <form method="POST" action="{{ route('properti.setup.aturan', $properti) }}" class="grid gap-4 sm:grid-cols-3">
                @csrf
                <x-field label="Tarif per Kg (Rp)" name="tarif_per_kg" type="number" :value="$aturanSaatIni['tarif_per_kg'] ?? 8000" :required="true" />
                <x-field label="Minimum Berat (Kg)" name="min_kg" type="number" step="0.1" :value="$aturanSaatIni['min_kg'] ?? 2" :required="true" />
                <x-field label="Kuota Bulanan (Kg)" name="kuota_kg" type="number" step="0.1" :value="$aturanSaatIni['kuota_kg'] ?? 20" :required="true" />
                <x-field label="Ambang Mendekati (%)" name="ambang_mendekati_persen" type="number" :value="$aturanSaatIni['ambang_mendekati_persen'] ?? 75" :required="true" />
                <x-field label="Toleransi Lewat Kuota (Kg)" name="toleransi_kg" type="number" step="0.1" :value="$aturanSaatIni['toleransi_kg'] ?? 1" :required="true" />
                <x-field label="Pembulatan Tagihan (Rp)" name="pembulatan" type="number" :value="$aturanSaatIni['pembulatan'] ?? 1000" :required="true" />

                <x-field label="Mode Kuota" name="mode_kuota" :required="true">
                    <select id="mode_kuota" name="mode_kuota" class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink">
                        <option value="penghuni" @selected(($aturanSaatIni['mode_kuota'] ?? 'penghuni') === 'penghuni')>Per Penghuni</option>
                        <option value="tanpa_kuota" @selected(($aturanSaatIni['mode_kuota'] ?? '') === 'tanpa_kuota')>Tanpa Kuota</option>
                    </select>
                </x-field>

                <x-field label="Batas Jam Edit Penjaga" name="jam_edit_penjaga" type="number" :value="$aturanSaatIni['jam_edit_penjaga'] ?? 24" :required="true" />

                <div class="flex items-end pb-1">
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-ink">
                        <input
                            type="checkbox"
                            name="penjaga_kelola_kontrak"
                            value="1"
                            @checked(($aturanSaatIni['penjaga_kelola_kontrak'] ?? '0') === '1')
                            class="h-4 w-4 rounded border-line-strong text-maroon focus:ring-maroon"
                        >
                        <span>Izinkan penjaga kelola kontrak</span>
                    </label>
                </div>

                <div class="sm:col-span-3 flex justify-end">
                    <button type="submit" class="min-h-11 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah">
                        Simpan Aturan Properti
                    </button>
                </div>
            </form>
        </x-kartu>

        {{-- Langkah 4: Tunjuk Admin & Penjaga + Aktivasi --}}
        <x-kartu judul="Langkah 4 · Penugasan Staf & Aktivasi" subjudul="Tetapkan 1 admin aktif dan penjaga untuk properti ini">
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

                <form method="POST" action="{{ route('pengguna.store') }}" class="grid gap-3 rounded-xl border border-line bg-alt p-4 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="properti_id" value="{{ $properti->id }}">
                    <input type="hidden" name="setup_properti_id" value="{{ $properti->id }}">
                    <x-field label="Nama Karyawan" name="name" id="wizard_name" :required="true" placeholder="Nama admin atau penjaga" />
                    <x-field label="Username" name="username" id="wizard_username" :required="true" placeholder="Huruf kecil tanpa spasi" />
                    <x-field label="Password Awal" name="password" id="wizard_password" type="password" :required="true" placeholder="Minimal 8 karakter" />
                    <x-field label="Peran" name="role" id="wizard_role" :required="true">
                        <select id="wizard_role" name="role" class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink">
                            @if (! $siapAdmin)
                                <option value="admin">Admin Properti</option>
                            @endif
                            <option value="penjaga">Penjaga</option>
                        </select>
                    </x-field>

                    <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-2 pt-1">
                        <a href="{{ route('pengguna.index') }}" class="text-xs font-bold text-maroon hover:underline">
                            Kelola lengkap di menu Pengguna →
                        </a>
                        <button type="submit" class="min-h-11 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken">
                            + Tambah Staf Properti
                        </button>
                    </div>
                </form>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                    <div class="text-xs text-muted">
                        @if ($properti->status === 'aktif')
                            Properti ini sudah berstatus <strong class="text-ok">Aktif</strong> dan siap digunakan staf.
                        @elseif ($siapKamar && $siapAdmin)
                            Seluruh syarat minimum terpenuhi. Anda dapat mengaktifkan properti sekarang.
                        @else
                            Lengkapi minimal <strong>1 kamar aktif</strong> dan <strong>1 admin properti aktif</strong> untuk mengaktifkan.
                        @endif
                    </div>

                    @if ($properti->status !== 'aktif')
                        <form method="POST" action="{{ route('properti.setup.aktifkan', $properti) }}">
                            @csrf
                            <button
                                type="submit"
                                class="min-h-11 rounded-xl bg-gold px-5 text-sm font-bold text-white hover:bg-gold-bright"
                            >
                                Aktifkan Properti
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </x-kartu>
    </div>
</x-app-layout>
