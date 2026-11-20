<x-app-layout judul="Properti" subjudul="Pilih properti untuk dikelola atau tambah baru">
    <x-slot:aksiHeader>
        <button
            type="button"
            data-modal-open="modal-tambah-properti"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-maroon px-3.5 text-xs font-bold text-white shadow-xs transition-colors hover:bg-maroon-cerah sm:px-5 sm:text-sm"
        >
            <i class="fas fa-plus text-xs" aria-hidden="true"></i>
            <span>Tambah</span>
        </button>
    </x-slot:aksiHeader>

    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($daftarPropertiKelola as $item)
                <article class="flex flex-col justify-between rounded-2xl border border-line bg-surface p-4 shadow-xs transition hover:border-line-strong">
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-maroon/10 text-maroon">
                            <i class="fas fa-building text-lg" aria-hidden="true"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h2 class="text-base font-bold text-ink">{{ $item->nama }}</h2>
                                <x-lencana :status="$item->status" />
                            </div>

                            <p class="mt-1 text-xs text-muted">
                                {{ $item->alamat ?: 'Alamat belum dilengkapi' }}
                            </p>

                            @if ($item->deskripsi)
                                <p class="mt-1 text-xs text-subtle">{{ $item->deskripsi }}</p>
                            @endif

                            <div class="mt-2.5 flex flex-wrap gap-3 text-xs text-muted">
                                <span>
                                    <i class="fas fa-door-closed mr-1 text-subtle" aria-hidden="true"></i>
                                    {{ $item->kamar_count ?? 0 }} kamar
                                </span>
                                <span>
                                    <i class="fas fa-users mr-1 text-subtle" aria-hidden="true"></i>
                                    {{ $item->penghuni_count ?? 0 }} penghuni
                                </span>
                                <span>
                                    <i class="fas fa-user-shield mr-1 text-subtle" aria-hidden="true"></i>
                                    {{ $item->adminAktif?->name ?? 'Belum ada admin' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                        <form method="POST" action="{{ route('properti.pilih', $item) }}" class="flex-1">
                            @csrf
                            <button
                                type="submit"
                                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-maroon px-4 text-xs font-bold text-white transition-colors hover:bg-maroon-cerah"
                            >
                                <i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>
                                <span>{{ $propertiAktif?->id === $item->id ? 'Buka (Sedang Aktif)' : 'Buka Properti' }}</span>
                            </button>
                        </form>

                        @can('update', $item)
                            <a
                                href="{{ route('properti.edit', $item) }}"
                                title="Edit properti"
                                aria-label="Edit {{ $item->nama }}"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-line-strong bg-surface text-maroon hover:bg-sunken"
                            >
                                <i class="fas fa-pen text-sm" aria-hidden="true"></i>
                            </a>

                            <a
                                href="{{ route('properti.setup.tampilkan', $item) }}"
                                title="Setup properti"
                                aria-label="Setup {{ $item->nama }}"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-line-strong bg-surface text-ink hover:bg-sunken"
                            >
                                <i class="fas fa-sliders text-sm" aria-hidden="true"></i>
                            </a>
                        @endcan
                    </div>
                </article>
            @empty
                <x-kartu class="md:col-span-2 xl:col-span-3">
                    <p class="text-sm text-muted">Belum ada properti terdaftar. Tekan tombol <strong>+ Tambah</strong> untuk membuat properti pertama.</p>
                </x-kartu>
            @endforelse
        </div>
    </div>

    <x-modal id="modal-tambah-properti" judul="Tambah Properti Baru">
        <form
            method="POST"
            action="{{ route('properti.store') }}"
            data-track-dirty
            data-draft-key="tambah-properti"
            class="space-y-4"
        >
            @csrf
            <x-field label="Nama Properti" name="nama" :required="true" placeholder="Mis. Dbata Residence 2" />
            <x-field label="Nama Properti di Laporan" name="nama_properti_laporan" :required="true" placeholder="Mis. Dbata Residence 2" />
            <x-field label="Alamat" name="alamat" placeholder="Alamat lengkap properti (opsional)" />
            <x-field label="No WhatsApp Admin" name="no_wa_admin" placeholder="Contoh: 628xxxx" />
            <x-field label="Deskripsi" name="deskripsi" placeholder="Tagline atau catatan singkat (opsional)" />
            <x-field label="Status" name="status" :required="true">
                <select
                    id="status"
                    name="status"
                    class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-sm text-ink focus:border-maroon focus:outline-none"
                >
                    <option value="draf" selected>Draf (lanjut ke Wizard Setup)</option>
                    <option value="aktif">Aktif</option>
                    <option value="arsip">Arsip</option>
                </select>
            </x-field>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    data-modal-close
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
