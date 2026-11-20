<x-app-layout :judul="$properti->nama" :subjudul="$properti->alamat ?: 'Alamat belum diisi'">
    <x-slot:aksiHeader>
        <x-lencana :status="$properti->status" />
        @can('update', $properti)
            <a
                href="{{ route('properti.setup.tampilkan', $properti) }}"
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-maroon px-3.5 text-xs font-bold text-white hover:bg-maroon-cerah sm:px-4"
            >
                Setup
            </a>
        @endcan
    </x-slot:aksiHeader>

    <div class="space-y-4">
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
