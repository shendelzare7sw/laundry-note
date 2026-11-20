<x-app-layout :judul="'Edit ' . $properti->nama">
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('properti.index') }}"
                    aria-label="Kembali ke daftar properti"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-line-strong bg-surface text-ink hover:bg-sunken"
                >
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </a>
                <span class="h-7 w-1 rounded-full bg-gold" aria-hidden="true"></span>
                <div>
                    <h1 class="text-xl font-bold text-ink">Edit Properti</h1>
                    <p class="text-xs text-muted">{{ $properti->nama }}</p>
                </div>
            </div>

            <a
                href="{{ route('properti.setup.tampilkan', $properti) }}"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line-strong bg-surface px-4 text-xs font-bold text-ink hover:bg-sunken"
            >
                <i class="fas fa-sliders text-maroon" aria-hidden="true"></i>
                <span>Wizard Setup Kamar &amp; Staf</span>
            </a>
        </div>

        <x-kartu>
            <form
                method="POST"
                action="{{ route('properti.update', $properti) }}"
                data-track-dirty
                data-draft-key="edit-properti-{{ $properti->id }}"
                data-confirm-title="Simpan Perubahan Properti?"
                data-confirm-message="Perubahan data properti atau status aktif akan memengaruhi akses staf properti ini."
                data-confirm-label="Ya, Simpan"
                data-confirm-variant="maroon"
                class="grid gap-4 md:grid-cols-2"
            >
                @csrf
                @method('PUT')

                <x-field label="Nama Properti" name="nama" :value="$properti->nama" :required="true" />
                <x-field label="Nama Properti di Laporan" name="nama_properti_laporan" :value="$properti->nama_properti_laporan" :required="true" />

                <div class="md:col-span-2">
                    <x-field label="Alamat" name="alamat" :value="$properti->alamat ?? ''" placeholder="Alamat lengkap properti" />
                </div>

                <x-field
                    label="No WhatsApp Admin"
                    name="no_wa_admin"
                    :value="$properti->no_wa_admin ?? ''"
                    placeholder="Contoh: 62815xxxx"
                    hint="Contoh: 628xxxx."
                />

                <x-field
                    label="Deskripsi"
                    name="deskripsi"
                    :value="$properti->deskripsi ?? ''"
                    placeholder="Keterangan singkat properti"
                />

                <div class="md:col-span-2 rounded-xl border border-line bg-alt p-4">
                    <x-field
                        label="Status Aktif & Operasional"
                        name="status"
                        id="edit-status"
                        :required="true"
                        hint="Mengubah ke Draf atau Nonaktif (Arsip) langsung mencabut sesi & akses login staf properti ini, tetapi seluruh data laundry, kontrak, penghuni, dan kamar tetap tersimpan."
                    >
                        <select
                            id="edit-status"
                            name="status"
                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none"
                        >
                            <option value="aktif" @selected(old('status', $properti->status) === 'aktif')>Aktif — Operasional berjalan & staf bisa login</option>
                            <option value="draf" @selected(old('status', $properti->status) === 'draf')>Draf — Dalam penyusunan setup, akses staf dikunci</option>
                            <option value="arsip" @selected(old('status', $properti->status) === 'arsip')>Nonaktif / Arsip — Operasional berhenti sementara, data historis aman</option>
                        </select>
                    </x-field>
                </div>

                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end md:col-span-2">
                    <a
                        href="{{ route('properti.index') }}"
                        class="inline-flex min-h-12 items-center justify-center rounded-full border border-line-strong bg-surface px-6 text-sm font-bold text-ink transition-colors hover:bg-sunken"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        class="flex min-h-12 flex-1 items-center justify-center rounded-full bg-maroon px-6 text-sm font-bold text-white transition-colors hover:bg-maroon-cerah sm:flex-initial sm:min-w-52"
                    >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </x-kartu>
    </div>
</x-app-layout>
