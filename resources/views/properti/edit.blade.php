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

                <div class="flex items-center justify-between gap-4 rounded-xl border border-line bg-alt p-4 md:col-span-2">
                    <div>
                        <div class="text-sm font-bold text-ink">Status Aktif</div>
                        <p class="text-xs text-muted">Properti nonaktif tidak muncul untuk staff.</p>
                    </div>

                    <label class="relative inline-flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            name="status_aktif"
                            value="1"
                            @checked(old('status', $properti->status) === 'aktif')
                            class="peer sr-only"
                        >
                        <span class="h-7 w-12 rounded-full bg-line-strong transition-colors peer-checked:bg-maroon peer-focus-visible:outline-2 peer-focus-visible:outline-maroon"></span>
                        <span class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow-xs transition-transform peer-checked:translate-x-5"></span>
                    </label>
                </div>

                <div class="md:col-span-2 pt-2">
                    <button
                        type="submit"
                        class="flex min-h-12 w-full items-center justify-center rounded-full bg-maroon px-6 text-sm font-bold text-white transition-colors hover:bg-maroon-cerah"
                    >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </x-kartu>
    </div>
</x-app-layout>
