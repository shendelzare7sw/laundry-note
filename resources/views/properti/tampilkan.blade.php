<x-app-layout :judul="$properti->nama">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-ink">{{ $properti->nama }}</h1>
                <p class="text-sm text-muted">{{ $properti->alamat ?: 'Alamat belum diisi' }}</p>
            </div>
            <x-lencana :status="$properti->status" />
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
