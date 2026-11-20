@php
    $isEdit = $laundry !== null;
    $beratLama = $laundry?->berat_kg !== null
        ? str_replace('.', ',', rtrim(rtrim((string) $laundry->berat_kg, '0'), '.'))
        : '';
    $biayaUtamaAwal = old('biaya_utama', $laundry?->biaya_utama !== null ? (string) $laundry->biaya_utama : '');
    $biayaManualAwal = old('_token') !== null
        ? ($biayaUtamaAwal !== null && trim((string) $biayaUtamaAwal) !== '')
        : (bool) ($laundry?->biaya_manual ?? false);

    $itemAwal = old('item');
    if (! is_array($itemAwal)) {
        $itemAwal = $laundry
            ? $laundry->item->map(fn ($i): array => [
                'nama' => $i->nama,
                'jumlah' => $i->jumlah,
                'berat_kg' => $i->berat_kg !== null
                    ? str_replace('.', ',', rtrim(rtrim((string) $i->berat_kg, '0'), '.'))
                    : '',
                'biaya' => $i->biaya,
            ])->all()
            : [];
    }

    $konfigLaundry = [
        'jenis' => old('jenis', $laundry?->jenis ?? 'penghuni'),
        'tanggal' => $tanggalAwal,
        'penghuniId' => old('penghuni_id', $penghuniAwalId),
        'daftarPenghuni' => $daftarPenghuni->values()->all(),
        'beratKg' => old('berat_kg', $beratLama),
        'biayaUtama' => (string) ($biayaUtamaAwal ?? ''),
        'biayaManual' => $biayaManualAwal,
        'tarifPerKg' => $aturanSaatIni['tarif_per_kg'],
        'minKgCenti' => $aturanSaatIni['min_kg_centi'],
        'konfirmasiBeratBesar' => (bool) old('konfirmasi_berat_besar', false),
        'maksItem' => $maksItem,
        'item' => array_values($itemAwal),
        'kamarAwal' => $laundry?->kamar?->nomor,
        'endpointInfo' => route('laundry.info-kuota'),
        'kecualiId' => $laundry?->id,
    ];
@endphp

<x-app-layout
    :judul="$isEdit ? 'Ubah Catatan Laundry' : 'Catat Laundry Baru'"
    :subjudul="$propertiAktif->nama"
>
    <x-slot:aksiHeader>
        <a
            href="{{ route('laundry.index') }}"
            class="inline-flex min-h-11 items-center gap-2 rounded-full border border-line-strong bg-surface px-4 text-xs font-bold text-ink hover:bg-sunken sm:text-sm"
        >
            <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i>
            <span>Kembali</span>
        </a>
    </x-slot:aksiHeader>

    <div class="mx-auto max-w-3xl space-y-4">
        <form
            id="form-laundry"
            method="POST"
            action="{{ $isEdit ? route('laundry.update', $laundry) : route('laundry.store') }}"
            x-data="formLaundry(@js($konfigLaundry))"
            x-on:draf-pulihkan="pulihkanDraf($event.detail)"
            data-track-dirty
            data-draft-key="{{ $isEdit ? 'ubah-laundry-' . $laundry->id : 'tambah-laundry-' . $propertiAktif->id }}"
            @if ($isEdit)
                data-confirm-title="Simpan Perubahan Catatan Laundry?"
                data-confirm-message="Perubahan pada catatan laundry ini akan disimpan dan kuota penghuni diperbarui otomatis."
                data-confirm-label="Ya, Simpan Perubahan"
                data-confirm-variant="maroon"
            @endif
            class="space-y-4"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif
            <input type="hidden" name="ref" value="{{ $ref }}">

            <x-kartu judul="Informasi Cucian" subjudul="Pilih pemilik, tanggal pencatatan, berat cucian utama, atau tambahkan item terpisah.">
                <div class="space-y-4">
                    <p class="text-xs text-muted">
                        Kolom bertanda <span class="font-bold text-danger" aria-hidden="true">*</span> wajib diisi.
                    </p>

                    {{-- Jenis Catatan (Radio Segmented Control Aksesibel) --}}
                    <fieldset class="space-y-1.5" data-field-wrapper data-field-label="Jenis Catatan" data-field-name="jenis">
                        <legend class="block text-sm font-medium text-ink">
                            Jenis Catatan <span class="text-danger" aria-hidden="true">*</span>
                        </legend>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (\App\Models\Laundry::DAFTAR_JENIS as $kodeJenis => $labelJenis)
                                <label
                                    class="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 text-center text-xs font-bold transition-colors focus-within:ring-2 focus-within:ring-maroon/30 sm:text-sm"
                                    :class="jenis === '{{ $kodeJenis }}' ? 'border-maroon bg-maroon text-white shadow-xs' : 'border-line-strong bg-alt text-ink hover:bg-sunken'"
                                >
                                    <input
                                        type="radio"
                                        name="jenis"
                                        value="{{ $kodeJenis }}"
                                        x-model="jenis"
                                        x-on:change="saatUbahJenis"
                                        class="sr-only"
                                    >
                                    <span>{{ $labelJenis }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p data-field-error="jenis" class="text-xs font-medium text-danger {{ $errors->has('jenis') ? '' : 'hidden' }}">{{ $errors->first('jenis') }}</p>
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        {{-- Tanggal --}}
                        <x-field
                            label="Tanggal"
                            name="tanggal"
                            id="laundry-tanggal"
                            type="date"
                            :max="$maksTanggal"
                            x-model="tanggal"
                            x-on:change="saatUbahTanggal"
                            class="min-h-12 text-base sm:text-sm"
                            :required="true"
                        />

                        {{-- Pilih Penghuni (Aktif saat jenis === 'penghuni') --}}
                        <div class="space-y-1.5" data-field-wrapper data-field-label="Penghuni" data-field-name="penghuni_id" x-show="jenis === 'penghuni'">
                            <label for="laundry-penghuni" class="block text-sm font-medium text-ink">
                                Penghuni <span class="text-danger" aria-hidden="true">*</span>
                            </label>

                            <input
                                id="cari-penghuni"
                                type="search"
                                x-model="cariPenghuni"
                                placeholder="Saring kamar atau nama..."
                                autocorrect="off"
                                spellcheck="false"
                                :disabled="jenis !== 'penghuni'"
                                class="block min-h-10 w-full rounded-xl border border-line bg-alt px-3 text-sm text-ink placeholder:text-subtle focus:border-maroon focus:bg-surface focus:outline-none"
                            >

                            <select
                                id="laundry-penghuni"
                                name="penghuni_id"
                                x-model="penghuniId"
                                x-on:change="saatUbahPenghuni"
                                :disabled="jenis !== 'penghuni'"
                                :required="jenis === 'penghuni'"
                                aria-invalid="{{ $errors->has('penghuni_id') ? 'true' : 'false' }}"
                                class="block min-h-12 w-full rounded-xl border bg-surface px-3.5 text-base text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20 sm:text-sm {{ $errors->has('penghuni_id') ? 'border-danger' : 'border-line-strong' }}"
                            >
                                <option value="">-- Pilih penghuni --</option>
                                <template x-for="calon in penghuniTersaring" :key="calon.id">
                                    <option :value="String(calon.id)" :selected="String(calon.id) === String(penghuniId)" x-text="calon.label"></option>
                                </template>
                            </select>

                            <p id="laundry-penghuni-error" data-field-error="penghuni_id" class="text-xs font-medium text-danger {{ $errors->has('penghuni_id') ? '' : 'hidden' }}">{{ $errors->first('penghuni_id') }}</p>
                        </div>
                    </div>

                    {{-- Panel Info Kuota Live (selalu ada di DOM sebagai ARIA live region) --}}
                    <div
                        role="status"
                        aria-live="polite"
                        class="rounded-xl border px-3.5 py-2.5 text-xs font-medium sm:text-sm"
                        :class="galatTanggal || infoKuota?.status === 'lewat' ? 'border-danger/35 bg-danger/10 text-danger' : (infoKuota?.status === 'mendekati' ? 'border-gold/40 bg-gold-container text-on-gold' : 'border-line bg-alt text-muted')"
                    >
                        <div class="flex items-start gap-2">
                            <i
                                class="fas mt-0.5 shrink-0"
                                :class="galatTanggal || infoKuota?.status === 'lewat' ? 'fa-triangle-exclamation' : 'fa-circle-info'"
                                aria-hidden="true"
                            ></i>
                            <span x-text="teksBantuKuota">Memuat informasi kuota...</span>
                        </div>
                    </div>

                    {{-- Berat Utama & Biaya Utama --}}
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5" data-field-wrapper data-field-label="Berat Utama (Kg)" data-field-name="berat_kg">
                            <label for="laundry-berat" class="block text-sm font-medium text-ink">
                                Berat Utama (Kg)
                            </label>
                            <input
                                id="laundry-berat"
                                name="berat_kg"
                                type="text"
                                inputmode="decimal"
                                enterkeyhint="next"
                                x-model="beratKg"
                                x-on:input="saatUbahBerat"
                                placeholder="Contoh: 2,4 (kosongkan jika hanya item)"
                                aria-invalid="{{ $errors->has('berat_kg') ? 'true' : 'false' }}"
                                class="block min-h-12 w-full rounded-xl border bg-surface px-3.5 text-base text-ink placeholder:text-subtle focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20 sm:text-sm {{ $errors->has('berat_kg') ? 'border-danger' : 'border-line-strong' }}"
                            >
                            <p class="text-xs text-muted">
                                Tarif Rp{{ number_format($aturanSaatIni['tarif_per_kg'], 0, ',', '.') }}/kg · minimum {{ str_replace('.', ',', $aturanSaatIni['min_kg']) }} kg.
                            </p>
                            <p id="laundry-berat-error" data-field-error="berat_kg" class="text-xs font-medium text-danger {{ $errors->has('berat_kg') ? '' : 'hidden' }}">{{ $errors->first('berat_kg') }}</p>
                        </div>

                        <div class="space-y-1.5" data-field-wrapper data-field-label="Biaya Utama (Rp)" data-field-name="biaya_utama">
                            <div class="flex items-center justify-between gap-2">
                                <label for="laundry-biaya-utama" class="block text-sm font-medium text-ink">
                                    Biaya Utama (Rp)
                                </label>
                                <button
                                    type="button"
                                    x-show="biayaManual && biayaOtomatis !== null"
                                    x-on:click="kembalikanOtomatis"
                                    class="text-xs font-bold text-maroon hover:underline"
                                >
                                    Kembalikan ke otomatis
                                </button>
                            </div>
                            <input
                                id="laundry-biaya-utama"
                                name="biaya_utama"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                data-pattern-message="Biaya utama harus berupa angka bulat rupiah."
                                enterkeyhint="next"
                                x-model="biayaUtama"
                                x-on:input="saatUbahBiayaManual"
                                placeholder="Otomatis dari berat"
                                aria-invalid="{{ $errors->has('biaya_utama') ? 'true' : 'false' }}"
                                class="block min-h-12 w-full rounded-xl border bg-surface px-3.5 text-base text-ink placeholder:text-subtle focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20 sm:text-sm {{ $errors->has('biaya_utama') ? 'border-danger' : 'border-line-strong' }}"
                            >
                            <p class="text-xs" :class="biayaManual ? 'font-bold text-on-gold' : 'text-muted'">
                                <span x-show="biayaOtomatis === null">Otomatis terisi saat berat kg dimasukkan.</span>
                                <span x-show="biayaOtomatis !== null && !biayaManual" x-text="'Hitungan otomatis: Rp' + formatRupiah(biayaOtomatis)"></span>
                                <span x-show="biayaOtomatis !== null && biayaManual" x-text="'Biaya manual aktif (otomatis: Rp' + formatRupiah(biayaOtomatis) + ')'"></span>
                            </p>
                            <p id="laundry-biaya-utama-error" data-field-error="biaya_utama" class="text-xs font-medium text-danger {{ $errors->has('biaya_utama') ? '' : 'hidden' }}">{{ $errors->first('biaya_utama') }}</p>
                        </div>
                    </div>

                    {{-- Konfirmasi Berat Besar > 30 kg --}}
                    <div
                        x-show="butuhKonfirmasiBerat"
                        x-cloak
                        class="space-y-1.5 rounded-xl border border-danger/35 bg-danger/5 p-3.5"
                        data-field-wrapper
                        data-field-label="Konfirmasi berat besar"
                        data-field-name="konfirmasi_berat_besar"
                    >
                        <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm font-medium text-ink">
                            <input
                                id="laundry-konfirmasi-berat"
                                type="checkbox"
                                name="konfirmasi_berat_besar"
                                value="1"
                                x-model="konfirmasiBeratBesar"
                                :disabled="!butuhKonfirmasiBerat"
                                class="h-4 w-4 rounded border-line-strong text-maroon focus:ring-maroon"
                            >
                            <span>Saya yakin berat cucian di atas 30 kg ini sudah benar</span>
                        </label>
                        <p id="laundry-konfirmasi-berat-error" data-field-error="konfirmasi_berat_besar" class="text-xs font-medium text-danger {{ $errors->has('konfirmasi_berat_besar') ? '' : 'hidden' }}">{{ $errors->first('konfirmasi_berat_besar') }}</p>
                    </div>

                    {{-- Daftar Item Terpisah (Multi-item, 1 kolom harga per item + subtotal live) --}}
                    <fieldset class="space-y-3 rounded-2xl border border-line bg-alt p-3.5 sm:p-4">
                        <legend class="px-1 text-sm font-bold text-ink">
                            Item Bayar Terpisah (Tidak Masuk Kuota)
                        </legend>

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-muted">Tambahkan satu atau beberapa item terpisah (selimut, sprei, bed cover, dll.).</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($daftarItemPilihan as $pilihan)
                                    <button
                                        type="button"
                                        x-on:click="tambahItem('{{ $pilihan }}')"
                                        :disabled="item.length >= maksItem"
                                        class="inline-flex min-h-10 items-center gap-1 rounded-full border border-line-strong bg-surface px-3 text-xs font-bold text-ink hover:bg-sunken disabled:opacity-50"
                                    >
                                        <i class="fas fa-plus text-[10px] text-maroon" aria-hidden="true"></i>
                                        <span>{{ $pilihan }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if ($errors->has('item') || $errors->has('item.*'))
                            <div class="rounded-xl border border-danger/35 bg-danger/10 px-3 py-2 text-xs font-medium text-danger">
                                {{ $errors->first('item') ?: $errors->first('item.*') }}
                            </div>
                        @endif

                        <div x-show="item.length === 0" class="rounded-xl border border-dashed border-line-strong bg-surface/60 px-3.5 py-4 text-center text-xs text-muted">
                            Belum ada item terpisah. Ketuk salah satu tombol di atas jika ada cucian yang dibayar terpisah.
                        </div>

                        <div class="space-y-2.5" x-show="item.length > 0">
                            <template x-for="(baris, idx) in item" :key="baris._key">
                                <div class="grid gap-2.5 rounded-xl border border-line bg-surface p-3 sm:grid-cols-12 sm:items-start">
                                    <div class="sm:col-span-4">
                                        <label :for="'item-nama-' + idx" class="mb-1 block text-xs font-medium text-muted">
                                            Nama Item <span class="text-danger" aria-hidden="true">*</span>
                                        </label>
                                        <input
                                            :id="'item-nama-' + idx"
                                            :name="'item[' + idx + '][nama]'"
                                            type="text"
                                            maxlength="100"
                                            required
                                            data-field-label="Nama item terpisah"
                                            x-model="baris.nama"
                                            placeholder="Contoh: Selimut"
                                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-base text-ink focus:border-maroon focus:outline-none sm:text-sm"
                                        >
                                    </div>

                                    <div class="grid grid-cols-3 gap-2 sm:col-span-7">
                                        <div>
                                            <label :for="'item-jumlah-' + idx" class="mb-1 block text-xs font-medium text-muted">
                                                Jumlah <span class="text-danger" aria-hidden="true">*</span>
                                            </label>
                                            <input
                                                :id="'item-jumlah-' + idx"
                                                :name="'item[' + idx + '][jumlah]'"
                                                type="number"
                                                min="1"
                                                max="99"
                                                required
                                                data-field-label="Jumlah item terpisah"
                                                x-model="baris.jumlah"
                                                class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-base text-ink focus:border-maroon focus:outline-none sm:text-sm"
                                            >
                                        </div>

                                        <div>
                                            <label :for="'item-berat-' + idx" class="mb-1 block text-xs font-medium text-muted">
                                                Berat (kg)
                                            </label>
                                            <input
                                                :id="'item-berat-' + idx"
                                                :name="'item[' + idx + '][berat_kg]'"
                                                type="text"
                                                inputmode="decimal"
                                                data-field-label="Berat item terpisah"
                                                x-model="baris.berat_kg"
                                                placeholder="Opsional"
                                                class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-base text-ink focus:border-maroon focus:outline-none sm:text-sm"
                                            >
                                        </div>

                                        <div>
                                            <label :for="'item-biaya-' + idx" class="mb-1 block text-xs font-medium text-muted">
                                                Harga (Rp) <span class="text-danger" aria-hidden="true">*</span>
                                            </label>
                                            <input
                                                :id="'item-biaya-' + idx"
                                                :name="'item[' + idx + '][biaya]'"
                                                type="text"
                                                inputmode="numeric"
                                                pattern="[0-9]+"
                                                required
                                                data-field-label="Biaya item terpisah"
                                                data-pattern-message="Harga item harus berupa angka bulat rupiah."
                                                x-model="baris.biaya"
                                                placeholder="15000"
                                                class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-base text-ink focus:border-maroon focus:outline-none sm:text-sm"
                                            >
                                        </div>
                                    </div>

                                    <div class="flex justify-end sm:col-span-1 sm:pt-5">
                                        <button
                                            type="button"
                                            x-on:click="hapusItem(idx)"
                                            aria-label="Hapus item terpisah"
                                            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-danger/30 text-danger hover:bg-danger/10"
                                        >
                                            <i class="fas fa-trash-can text-sm" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div x-show="item.length > 0" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gold/40 bg-gold-container/70 px-3.5 py-2.5 text-xs font-bold text-on-gold sm:text-sm">
                            <span x-text="'Total Item Terpisah (' + item.length + ' baris · ' + totalJumlahItem + ' pcs)'"></span>
                            <span x-text="'Rp' + formatRupiah(totalTerpisah)">Rp0</span>
                        </div>
                    </fieldset>

                    {{-- Catatan --}}
                    <div class="space-y-1.5" data-field-wrapper data-field-label="Catatan" data-field-name="catatan">
                        <label for="laundry-catatan" class="block text-sm font-medium text-ink">Catatan</label>
                        <textarea
                            id="laundry-catatan"
                            name="catatan"
                            rows="2"
                            maxlength="500"
                            placeholder="Catatan tambahan (opsional)"
                            class="block w-full rounded-xl border border-line-strong bg-surface px-3.5 py-2.5 text-base text-ink placeholder:text-subtle focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20 sm:text-sm"
                        >{{ old('catatan', $laundry?->catatan ?? '') }}</textarea>
                        <p id="laundry-catatan-error" data-field-error="catatan" class="text-xs font-medium text-danger {{ $errors->has('catatan') ? '' : 'hidden' }}">{{ $errors->first('catatan') }}</p>
                    </div>
                </div>
            </x-kartu>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <a
                    href="{{ route('laundry.index') }}"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl border border-line-strong bg-surface px-5 text-sm font-bold text-ink hover:bg-sunken"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    data-submit-once
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-maroon px-6 text-sm font-bold text-white shadow-sm hover:bg-maroon-cerah disabled:opacity-60"
                >
                    <i class="fas fa-check text-xs" aria-hidden="true"></i>
                    <span>{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Catatan Laundry' }}</span>
                </button>
            </div>
        </form>

        @if ($isEdit && $riwayatAudit->isNotEmpty())
            <x-kartu judul="Riwayat Perubahan" subjudul="Jejak pencatatan dan perubahan pada catatan ini">
                <ol class="space-y-2.5 text-xs">
                    @foreach ($riwayatAudit as $log)
                        <li class="rounded-xl border border-line bg-alt px-3.5 py-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-bold text-ink">{{ str_replace('_', ' ', ucfirst($log->aksi)) }}</span>
                                <span class="text-muted">{{ $log->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="mt-0.5 text-muted">Oleh: {{ $log->pengguna?->name ?? 'Sistem' }}</p>
                        </li>
                    @endforeach
                </ol>
            </x-kartu>
        @endif
    </div>
</x-app-layout>
