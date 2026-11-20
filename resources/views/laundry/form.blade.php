<x-app-layout :judul="$laundry ? 'Ubah Catatan Laundry' : 'Catat Laundry'" :subjudul="$propertiAktif->nama">
    <div class="space-y-4">
        <x-kartu judul="Formulir Laundry">
            <p class="text-sm text-muted">Menyiapkan formulir pencatatan laundry.</p>
        </x-kartu>
    </div>
</x-app-layout>
