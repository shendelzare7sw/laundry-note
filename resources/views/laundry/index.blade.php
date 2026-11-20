<x-app-layout judul="Laundry" :subjudul="$propertiAktif?->nama ?? 'Daftar & catatan laundry'">
    <div class="space-y-4">
        <x-kartu judul="Modul Laundry (Kerangka Fase 1a)">
            <p class="text-sm text-muted">
                Daftar catatan laundry, filter periode, formulir tambah/ubah dengan hitung biaya otomatis, dan pemulihan data terhapus akan diimplementasikan pada Fase 3.
            </p>
        </x-kartu>
    </div>
</x-app-layout>
