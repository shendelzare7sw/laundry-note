<x-app-layout judul="Kelola Properti">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-ink">Kelola Properti</h1>
                <p class="text-sm text-muted">Daftar properti kos yang dikelola pada aplikasi ini</p>
            </div>

            <button
                type="button"
                x-data
                x-on:click="$dispatch('buka-modal', 'modal-tambah-properti')"
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-maroon px-4 py-2 text-sm font-bold text-white hover:bg-maroon-cerah"
            >
                + Tambah Properti
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @forelse ($daftarPropertiKelola as $item)
                <x-kartu :judul="$item->nama" :subjudul="$item->alamat ?: 'Alamat belum diisi'">
                    <x-slot:aksi>
                        <x-lencana :status="$item->status" />
                    </x-slot:aksi>

                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted">Judul Laporan</dt>
                            <dd class="font-medium text-ink">{{ $item->nama_properti_laporan }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted">Admin Aktif</dt>
                            <dd class="font-medium text-ink">{{ $item->adminAktif?->name ?? 'Belum ditetapkan' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a
                            href="{{ route('properti.tampilkan', $item) }}"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line-strong bg-surface px-3.5 text-xs font-bold text-ink hover:bg-sunken"
                        >
                            Detail
                        </a>
                        @can('update', $item)
                            <a
                                href="{{ route('properti.setup.tampilkan', $item) }}"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-maroon/25 bg-maroon/5 px-3.5 text-xs font-bold text-maroon hover:bg-maroon/10"
                            >
                                Setup
                            </a>
                        @endcan
                        <form method="POST" action="{{ route('properti.pilih', $item) }}">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-gold-container px-3.5 text-xs font-bold text-on-gold hover:bg-gold/25"
                            >
                                {{ $propertiAktif?->id === $item->id ? 'Sedang Aktif' : 'Jadikan Aktif' }}
                            </button>
                        </form>
                    </div>
                </x-kartu>
            @empty
                <x-kartu>
                    <p class="text-sm text-muted">Belum ada properti terdaftar.</p>
                </x-kartu>
            @endforelse
        </div>
    </div>

    <x-modal id="modal-tambah-properti" judul="Tambah Properti Baru">
        <form method="POST" action="{{ route('properti.store') }}" class="space-y-4">
            @csrf
            <x-field label="Nama Properti" name="nama" :required="true" placeholder="Mis. Dbata Residence 2" />
            <x-field label="Nama Properti di Laporan" name="nama_properti_laporan" :required="true" placeholder="Mis. Dbata Residence 2" />
            <x-field label="Alamat" name="alamat" placeholder="Alamat lengkap properti (opsional)" />
            <x-field label="Status" name="status" :required="true">
                <select
                    id="status"
                    name="status"
                    class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none"
                >
                    <option value="draf">Draf</option>
                    <option value="aktif" selected>Aktif</option>
                    <option value="arsip">Arsip</option>
                </select>
            </x-field>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    x-on:click="$dispatch('tutup-modal', 'modal-tambah-properti')"
                    class="min-h-11 rounded-xl border border-line-strong px-4 text-sm font-medium text-ink hover:bg-sunken"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="min-h-11 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                >
                    Simpan Properti
                </button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
