<x-app-layout judul="Catatan Laundry Terhapus" :subjudul="$propertiAktif->nama">
    <x-slot:aksiHeader>
        <a
            href="{{ route('laundry.index') }}"
            class="inline-flex min-h-11 items-center gap-2 rounded-full border border-line-strong bg-surface px-4 text-xs font-bold text-ink hover:bg-sunken sm:text-sm"
        >
            <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i>
            <span>Kembali ke Laundry</span>
        </a>
    </x-slot:aksiHeader>

    <div class="space-y-4">
        @if ($daftarTerhapus->isEmpty())
            <x-kartu>
                <div class="py-10 text-center">
                    <i class="fas fa-trash-can-arrow-up text-3xl text-subtle" aria-hidden="true"></i>
                    <p class="mt-3 text-sm font-bold text-ink">Tidak ada catatan terhapus</p>
                    <p class="mt-1 text-xs text-muted">Catatan laundry yang dihapus sementara akan muncul di sini dan dapat dipulihkan kapan saja.</p>
                </div>
            </x-kartu>
        @else
            <div class="space-y-3">
                @foreach ($daftarTerhapus as $baris)
                    @php
                        $beratTampil = $baris->berat_kg !== null
                            ? str_replace('.', ',', rtrim(rtrim((string) $baris->berat_kg, '0'), '.'))
                            : null;
                        $judulBaris = match ($baris->jenis) {
                            'gedung' => 'Laundry Gedung',
                            'tanpa_nama' => 'Tanpa Nama',
                            default => 'Kamar ' . ($baris->kamar?->nomor ?? '-'),
                        };
                    @endphp

                    <article class="rounded-2xl border border-line bg-surface p-4 shadow-xs">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-sm font-bold text-ink sm:text-base">
                                        {{ $judulBaris }}@if ($beratTampil !== null) · {{ $beratTampil }} kg @endif
                                    </h2>
                                    <x-lencana status="arsip">Terhapus</x-lencana>
                                </div>

                                <p class="text-xs text-muted">
                                    @if ($baris->jenis === 'penghuni' && $baris->penghuni)
                                        <strong>{{ $baris->penghuni->nama }}</strong> ·
                                    @endif
                                    Tanggal cuci: {{ $baris->tanggal->format('d/m/Y') }} ·
                                    Dihapus: {{ $baris->deleted_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}
                                </p>

                                @if ($baris->item->isNotEmpty())
                                    <p class="text-xs font-medium text-on-gold">
                                        Item terpisah: {{ $baris->ringkasanItem() }} (Rp{{ number_format($baris->item_biaya, 0, ',', '.') }})
                                    </p>
                                @endif
                            </div>

                            <form
                                method="POST"
                                action="{{ route('laundry.pulihkan', $baris->id) }}"
                                data-confirm-title="Pulihkan Catatan Laundry?"
                                data-confirm-message="Catatan ini akan dikembalikan ke daftar aktif dan ikut dihitung ulang ke kuota penghuni jika berlaku."
                                data-confirm-label="Ya, Pulihkan"
                                data-confirm-variant="maroon"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-maroon px-4 text-xs font-bold text-white hover:bg-maroon-cerah"
                                >
                                    <i class="fas fa-rotate-left text-xs" aria-hidden="true"></i>
                                    <span>Pulihkan</span>
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($daftarTerhapus->hasPages())
                <div class="pt-2">
                    {{ $daftarTerhapus->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
