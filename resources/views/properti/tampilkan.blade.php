<x-app-layout :judul="$properti->nama">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-ink">{{ $properti->nama }}</h1>
                <p class="text-sm text-muted">{{ $properti->alamat ?: 'Alamat belum diisi' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-lencana :status="$properti->status" />
                @can('update', $properti)
                    <a
                        href="{{ route('properti.setup.tampilkan', $properti) }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-maroon px-4 text-xs font-bold text-white hover:bg-maroon-cerah"
                    >
                        Setup Properti
                    </a>
                @endcan
            </div>
        </div>

        <x-kartu judul="Informasi Dasar">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-muted">Owner</dt>
                    <dd class="font-medium text-ink">{{ $properti->owner?->name ?? '-' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-muted">Admin Aktif</dt>
                    <dd class="font-medium text-ink">{{ $properti->adminAktif?->name ?? 'Belum ditetapkan' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-muted">Jumlah Penjaga</dt>
                    <dd class="font-medium text-ink">{{ $properti->penjaga->count() }} orang</dd>
                </div>
            </dl>
        </x-kartu>
    </div>
</x-app-layout>
