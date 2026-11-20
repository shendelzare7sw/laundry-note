<x-app-layout judul="Catatan Laundry Terhapus" :subjudul="$propertiAktif->nama">
    <div class="space-y-4">
        <x-kartu judul="Daftar Catatan Terhapus">
            <p class="text-sm text-muted">{{ $daftarTerhapus->total() }} catatan terhapus.</p>
        </x-kartu>
    </div>
</x-app-layout>
