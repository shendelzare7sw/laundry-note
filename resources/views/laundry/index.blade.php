<x-app-layout judul="Laundry" :subjudul="$propertiAktif->nama . ' · ' . $rentang['label']">
    <x-slot:aksiHeader>
        <div class="flex flex-wrap items-center gap-2">
            @if ($bolehLengkapiHarga && ($pratinjauLengkapiHarga['jumlah'] ?? 0) > 0)
                <button
                    type="button"
                    data-modal-open="modal-lengkapi-harga"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-gold/40 bg-gold-container px-3.5 text-xs font-bold text-on-gold hover:bg-gold/20 sm:text-sm"
                >
                    <i class="fas fa-wand-magic-sparkles text-xs" aria-hidden="true"></i>
                    <span>Lengkapi Harga ({{ $pratinjauLengkapiHarga['jumlah'] }})</span>
                </button>
            @endif

            @if ($bolehLihatTerhapus)
                <a
                    href="{{ route('laundry.terhapus') }}"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-line-strong bg-surface px-3.5 text-xs font-bold text-ink hover:bg-sunken sm:text-sm"
                >
                    <i class="fas fa-trash-can-arrow-up text-xs text-muted" aria-hidden="true"></i>
                    <span>Terhapus</span>
                    @if ($jumlahTerhapus > 0)
                        <span class="rounded-full bg-danger/10 px-1.5 py-0.5 text-[11px] font-bold text-danger">{{ $jumlahTerhapus }}</span>
                    @endif
                </a>
            @endif

            @if ($bolehTambah)
                <a
                    href="{{ route('laundry.create') }}"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-maroon px-4 text-xs font-bold text-white shadow-xs transition-colors hover:bg-maroon-cerah sm:px-5 sm:text-sm"
                >
                    <i class="fas fa-plus text-xs" aria-hidden="true"></i>
                    <span>Catat Laundry</span>
                </a>
            @endif
        </div>
    </x-slot:aksiHeader>

    <div class="space-y-4">
        @php
            $pilihanPeriode = [
                'hari_ini' => 'Hari ini',
                '7_hari' => '7 hari',
                'bulan_ini' => 'Bulan ini',
                'bulan_lalu' => 'Bulan lalu',
                'semua' => 'Semua',
                'rentang' => 'Dari–Sampai',
            ];
            $adaFilterAktif = $filter['periode'] !== 'bulan_ini' || $filter['cari'] !== '' || $filter['jenis'] !== '';
        @endphp

        {{-- Filter Periode & Pencarian --}}
        <search aria-label="Filter catatan laundry">
            <div class="rounded-2xl border border-line bg-surface p-3.5 shadow-xs space-y-3">
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Pilih periode waktu">
                    @foreach ($pilihanPeriode as $kode => $label)
                        @php
                            $tautanFilter = array_filter([
                                'periode' => $kode,
                                'cari' => $filter['cari'] !== '' ? $filter['cari'] : null,
                                'jenis' => $filter['jenis'] !== '' ? $filter['jenis'] : null,
                                'dari' => $kode === 'rentang' ? ($filter['dari'] ?? now('Asia/Jakarta')->startOfMonth()->toDateString()) : null,
                                'sampai' => $kode === 'rentang' ? ($filter['sampai'] ?? now('Asia/Jakarta')->toDateString()) : null,
                            ], fn ($v) => $v !== null);
                            $sedangDipilih = $filter['periode'] === $kode;
                        @endphp
                        <a
                            href="{{ route('laundry.index', $tautanFilter) }}"
                            @if ($sedangDipilih) aria-current="true" @endif
                            class="inline-flex min-h-10 items-center rounded-full border px-3.5 text-xs font-bold transition-colors {{ $sedangDipilih ? 'border-maroon bg-maroon text-white' : 'border-line-strong bg-alt text-muted hover:bg-sunken hover:text-ink' }}"
                        >
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('laundry.index') }}" class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-end">
                    <input type="hidden" name="periode" value="{{ $filter['periode'] }}">

                    @if ($filter['periode'] === 'rentang')
                        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-end">
                            <div>
                                <label for="filter-dari" class="mb-1 block text-xs font-medium text-muted">Dari tanggal</label>
                                <input
                                    id="filter-dari"
                                    name="dari"
                                    type="date"
                                    value="{{ $rentang['dari'] ?? '' }}"
                                    class="min-h-11 w-full rounded-xl border border-line-strong bg-alt px-3 text-base text-ink focus:border-maroon focus:bg-surface focus:outline-none sm:text-sm"
                                >
                            </div>
                            <div>
                                <label for="filter-sampai" class="mb-1 block text-xs font-medium text-muted">Sampai tanggal</label>
                                <input
                                    id="filter-sampai"
                                    name="sampai"
                                    type="date"
                                    value="{{ $rentang['sampai'] ?? '' }}"
                                    class="min-h-11 w-full rounded-xl border border-line-strong bg-alt px-3 text-base text-ink focus:border-maroon focus:bg-surface focus:outline-none sm:text-sm"
                                >
                            </div>
                        </div>
                    @endif

                    <div class="relative flex-1">
                        <label for="filter-cari" class="sr-only">Cari catatan laundry</label>
                        <i class="fas fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-subtle" aria-hidden="true"></i>
                        <input
                            id="filter-cari"
                            name="cari"
                            type="search"
                            value="{{ $filter['cari'] }}"
                            placeholder="Cari nama penghuni, kamar, item terpisah, pencatat..."
                            autocorrect="off"
                            spellcheck="false"
                            class="min-h-11 w-full rounded-xl border border-line-strong bg-alt pl-9 pr-3.5 text-base text-ink placeholder:text-subtle focus:border-maroon focus:bg-surface focus:outline-none focus:ring-2 focus:ring-maroon/15 sm:text-sm"
                        >
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="filter-jenis" class="sr-only">Jenis catatan</label>
                        <select
                            id="filter-jenis"
                            name="jenis"
                            class="min-h-11 rounded-xl border border-line-strong bg-alt px-3 text-base text-ink focus:border-maroon focus:bg-surface focus:outline-none sm:text-sm"
                        >
                            <option value="">Semua jenis</option>
                            @foreach (\App\Models\Laundry::DAFTAR_JENIS as $kodeJenis => $labelJenis)
                                <option value="{{ $kodeJenis }}" @selected($filter['jenis'] === $kodeJenis)>{{ $labelJenis }}</option>
                            @endforeach
                        </select>

                        <button
                            type="submit"
                            class="min-h-11 shrink-0 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                        >
                            Terapkan
                        </button>

                        @if ($adaFilterAktif)
                            <a
                                href="{{ route('laundry.index') }}"
                                class="inline-flex min-h-11 shrink-0 items-center rounded-xl border border-line-strong bg-surface px-3.5 text-sm font-medium text-muted hover:bg-sunken hover:text-ink"
                            >
                                Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </search>

        {{-- Kartu Ringkasan --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-ringkasan-tile
                label="Total Berat"
                :nilai="$ringkasan['total_berat_kg'] . ' kg'"
                :keterangan="$ringkasan['jumlah_catatan'] . ' catatan'"
            />
            <x-ringkasan-tile
                label="Kuota Gratis"
                :nilai="$ringkasan['kuota_gratis_kg'] . ' kg'"
                aksen="ok"
                keterangan="Pemakaian masuk jatah kuota"
            />
            <x-ringkasan-tile
                label="Biaya Utama"
                :nilai="'Rp' . number_format($ringkasan['total_biaya_utama'], 0, ',', '.')"
                keterangan="Nilai internal cucian kg"
            />
            <x-ringkasan-tile
                label="Bayar Terpisah"
                :nilai="'Rp' . number_format($ringkasan['total_bayar_terpisah'], 0, ',', '.')"
                aksen="gold"
                :keterangan="$ringkasan['jumlah_catatan_terpisah'] . ' catatan · ' . $ringkasan['jumlah_item_terpisah'] . ' item'"
            />
        </div>

        {{-- Banner Peringatan Kuota Bulan Ini --}}
        @if ($peringatanKuota->isNotEmpty())
            <div class="rounded-2xl border border-gold/40 bg-gold-container/70 p-3.5 text-xs text-on-gold sm:text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 font-bold">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        <span>{{ $peringatanKuota->count() }} penghuni mendekati atau melewati kuota bulan ini:</span>
                    </div>
                    <a href="{{ route('beranda') }}" class="font-bold text-maroon hover:underline">Lihat semua →</a>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($peringatanKuota->take(6) as $peringatan)
                        <a
                            href="{{ route('penghuni.show', $peringatan['penghuni']) }}"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-bold {{ $peringatan['kuota']['status'] === 'lewat' ? 'border-danger/30 bg-danger/10 text-danger' : 'border-gold/40 bg-surface text-on-gold' }}"
                        >
                            <span>Kamar {{ $peringatan['kamar'] }} · {{ $peringatan['penghuni']->nama }}</span>
                            <span>({{ $peringatan['kuota']['terpakai_kg'] }}/{{ $peringatan['kuota']['kuota_kg'] }} kg)</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($ringkasan['jumlah_tanpa_nama'] > 0)
            <div class="rounded-xl border border-warn/30 bg-gold-container/40 px-4 py-2.5 text-xs text-on-gold">
                <i class="fas fa-user-tag mr-1" aria-hidden="true"></i>
                Ada <strong>{{ $ringkasan['jumlah_tanpa_nama'] }}</strong> catatan <strong>Tanpa nama</strong> pada filter ini. Gunakan tombol <strong>Tetapkan Pemilik</strong> agar masuk ke kuota penghuni terkait.
            </div>
        @endif

        {{-- Daftar Catatan Laundry --}}
        @if ($daftar->isEmpty())
            <x-kartu>
                <div class="py-10 text-center">
                    <i class="fas fa-shirt text-3xl text-subtle" aria-hidden="true"></i>
                    <p class="mt-3 text-sm font-bold text-ink">Belum ada catatan laundry</p>
                    <p class="mt-1 text-xs text-muted">Belum ada catatan yang cocok dengan periode atau kata kunci pencarian ini.</p>
                    @if ($bolehTambah)
                        <a
                            href="{{ route('laundry.create') }}"
                            class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-full bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah"
                        >
                            <i class="fas fa-plus text-xs" aria-hidden="true"></i>
                            <span>Catat Laundry Baru</span>
                        </a>
                    @endif
                </div>
            </x-kartu>
        @else
            <div class="space-y-3">
                @foreach ($daftar as $baris)
                    @php
                        $meta = $metaBaris[$baris->id] ?? null;
                        $isLewat = (bool) ($meta['lewat_kuota'] ?? false);
                        $kuota = $meta['kuota'] ?? null;
                        $beratTampil = $baris->berat_kg !== null
                            ? str_replace('.', ',', rtrim(rtrim((string) $baris->berat_kg, '0'), '.'))
                            : null;
                        $judulBaris = match ($baris->jenis) {
                            'gedung' => 'Laundry Gedung',
                            'tanpa_nama' => 'Tanpa Nama',
                            default => 'Kamar ' . ($baris->kamar?->nomor ?? '-'),
                        };
                        $ikonJenis = match ($baris->jenis) {
                            'gedung' => 'fa-building',
                            'tanpa_nama' => 'fa-circle-question',
                            default => 'fa-shirt',
                        };
                        $bolehUbah = auth()->user()->can('update', $baris);
                        $bolehHapus = auth()->user()->can('delete', $baris);
                        $bolehTetapkan = $baris->jenis === 'tanpa_nama' && auth()->user()->can('tetapkanPemilik', $baris);
                    @endphp

                    <article class="rounded-2xl border p-4 shadow-xs transition {{ $isLewat ? 'border-danger/40 border-l-4 border-l-danger bg-danger/5' : 'border-line bg-surface' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $isLewat ? 'bg-danger/15 text-danger' : ($baris->jenis === 'gedung' ? 'bg-sunken text-ink' : ($baris->jenis === 'tanpa_nama' ? 'bg-gold-container text-on-gold' : 'bg-maroon/10 text-maroon')) }}">
                                    <i class="fas {{ $ikonJenis }} text-base" aria-hidden="true"></i>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-bold text-ink sm:text-base">
                                            {{ $judulBaris }}@if ($beratTampil !== null) · {{ $beratTampil }} kg @endif
                                        </h2>

                                        @if ($isLewat)
                                            <x-lencana status="lewat">
                                                <i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                                                <span>Lewat kuota</span>
                                            </x-lencana>
                                        @endif

                                        @if ($baris->biaya_manual)
                                            <span class="inline-flex items-center rounded-full border border-gold/40 bg-gold-container px-2 py-0.5 text-[11px] font-bold text-on-gold">
                                                Manual
                                            </span>
                                        @endif

                                        @if ($baris->jenis === 'tanpa_nama')
                                            <x-lencana status="warn">Tanpa nama</x-lencana>
                                        @elseif ($baris->jenis === 'gedung')
                                            <span class="inline-flex items-center rounded-full border border-line-strong bg-sunken px-2 py-0.5 text-[11px] font-medium text-muted">
                                                Gedung
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-muted">
                                        @if ($baris->jenis === 'penghuni' && $baris->penghuni)
                                            <a href="{{ route('penghuni.show', $baris->penghuni) }}" class="font-bold text-ink hover:text-maroon hover:underline">
                                                {{ $baris->penghuni->nama }}
                                            </a>
                                            <span aria-hidden="true">·</span>
                                        @endif
                                        <span>{{ $baris->tanggal->format('d/m/Y') }}</span>
                                        @if ($baris->pencatat)
                                            <span aria-hidden="true">·</span>
                                            <span>Dicatat: {{ $baris->pencatat->name }}</span>
                                        @endif
                                    </div>

                                    @if ($kuota && ($kuota['mode_kuota'] ?? 'penghuni') === 'penghuni')
                                        <p class="mt-1 text-xs font-medium {{ $kuota['status'] === 'lewat' ? 'text-danger' : ($kuota['status'] === 'mendekati' ? 'text-on-gold' : 'text-ok') }}">
                                            Kuota {{ $kuota['label_bulan'] }}: {{ $kuota['terpakai_kg'] }}/{{ $kuota['kuota_kg'] }} kg
                                            @if ($kuota['status'] === 'lewat')
                                                · Lewat kuota{{ $kuota['di_bawah_toleransi'] ? ' (di bawah toleransi)' : '' }}
                                            @elseif ($kuota['status'] === 'mendekati')
                                                · Mendekati kuota
                                            @else
                                                · Normal
                                            @endif
                                        </p>
                                    @endif

                                    @if ($baris->item->isNotEmpty())
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($baris->item as $itemTerpisah)
                                                <span class="inline-flex items-center gap-1 rounded-lg border border-gold/30 bg-gold-container/60 px-2.5 py-1 text-xs font-medium text-on-gold">
                                                    <span>{{ $itemTerpisah->nama }} ×{{ $itemTerpisah->jumlah }}</span>
                                                    <span class="font-bold">· Rp{{ number_format($itemTerpisah->biaya, 0, ',', '.') }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($baris->catatan)
                                        <p class="mt-1.5 text-xs text-muted">{{ $baris->catatan }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex w-full flex-row items-center justify-between gap-3 border-t border-line pt-2.5 sm:w-auto sm:flex-col sm:items-end sm:border-t-0 sm:pt-0">
                                <div class="text-left sm:text-right">
                                    @if ($baris->biaya_utama !== null && $baris->biaya_utama > 0)
                                        <div class="text-sm font-bold text-ink">
                                            Rp{{ number_format($baris->biaya_utama, 0, ',', '.') }}
                                        </div>
                                    @elseif ($baris->berat_kg !== null)
                                        <div class="text-xs font-medium text-danger">Belum ada harga</div>
                                    @endif

                                    @if ($baris->item_biaya > 0)
                                        <div class="text-xs font-bold text-on-gold">
                                            Terpisah Rp{{ number_format($baris->item_biaya, 0, ',', '.') }}
                                        </div>
                                    @endif
                                </div>

                                @if ($bolehTetapkan || $bolehUbah || $bolehHapus)
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($bolehTetapkan)
                                            <button
                                                type="button"
                                                data-tetapkan-action="{{ route('laundry.tetapkan-pemilik', $baris) }}"
                                                data-tetapkan-ringkasan="Tanggal {{ $baris->tanggal->format('d/m/Y') }}{{ $beratTampil ? ' · ' . $beratTampil . ' kg' : '' }}"
                                                class="inline-flex min-h-11 items-center gap-1 rounded-xl border border-gold/40 bg-gold-container px-3 text-xs font-bold text-on-gold hover:bg-gold/20"
                                            >
                                                <i class="fas fa-user-check" aria-hidden="true"></i>
                                                <span>Tetapkan Pemilik</span>
                                            </button>
                                        @endif

                                        @if ($bolehUbah)
                                            <a
                                                href="{{ route('laundry.edit', $baris) }}"
                                                class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-line-strong bg-surface px-3 text-xs font-bold text-ink hover:bg-sunken"
                                            >
                                                <i class="fas fa-pen text-[11px]" aria-hidden="true"></i>
                                                <span>Ubah</span>
                                            </a>
                                        @endif

                                        @if ($bolehHapus)
                                            <form
                                                method="POST"
                                                action="{{ route('laundry.destroy', $baris) }}"
                                                data-confirm-title="Hapus Catatan Laundry?"
                                                data-confirm-message="Catatan tanggal {{ $baris->tanggal->format('d/m/Y') }} ({{ $judulBaris }}{{ $beratTampil ? ' · ' . $beratTampil . ' kg' : '' }}) akan dipindahkan ke daftar terhapus dan kuota penghuni dihitung ulang."
                                                data-confirm-label="Ya, Hapus"
                                                data-confirm-variant="danger"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-danger/30 bg-surface px-3 text-xs font-bold text-danger hover:bg-danger/10"
                                                >
                                                    <i class="fas fa-trash-can text-[11px]" aria-hidden="true"></i>
                                                    <span>Hapus</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($daftar->hasPages())
                <div class="pt-2">
                    {{ $daftar->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Modal Lengkapi Harga Kosong --}}
    @if ($bolehLengkapiHarga && ($pratinjauLengkapiHarga['jumlah'] ?? 0) > 0)
        <x-modal id="modal-lengkapi-harga" judul="Lengkapi Harga Otomatis">
            <div class="space-y-4">
                <div class="rounded-xl border border-gold/40 bg-gold-container/60 p-3.5 text-sm text-on-gold">
                    <p>
                        Ditemukan <strong>{{ $pratinjauLengkapiHarga['jumlah'] }}</strong> catatan laundry yang memiliki berat tetapi biaya utamanya masih kosong atau Rp0.
                    </p>
                    <p class="mt-1 font-bold">
                        Estimasi total nilai yang akan diisi: Rp{{ number_format($pratinjauLengkapiHarga['total'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="max-h-56 space-y-1.5 overflow-y-auto rounded-xl border border-line bg-alt p-3 text-xs">
                    @foreach ($pratinjauLengkapiHarga['daftar']->take(10) as $contoh)
                        <div class="flex items-center justify-between gap-2 border-b border-line pb-1.5 last:border-b-0 last:pb-0">
                            <span class="text-muted">{{ \Carbon\CarbonImmutable::parse($contoh['tanggal'])->format('d/m/Y') }} · {{ str_replace('.', ',', rtrim(rtrim($contoh['berat_kg'], '0'), '.')) }} kg</span>
                            <span class="font-bold text-ink">Rp{{ number_format($contoh['biaya_baru'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                    @if ($pratinjauLengkapiHarga['jumlah'] > 10)
                        <p class="pt-1 text-center text-muted">... dan {{ $pratinjauLengkapiHarga['jumlah'] - 10 }} catatan lainnya.</p>
                    @endif
                </div>

                <p class="text-xs text-muted">
                    Catatan yang sudah memiliki biaya manual atau nilai > 0 tidak akan diubah.
                </p>

                <form
                    method="POST"
                    action="{{ route('laundry.lengkapi-harga') }}"
                    data-confirm-title="Isi Harga Otomatis Sekarang?"
                    data-confirm-message="Sebanyak {{ $pratinjauLengkapiHarga['jumlah'] }} catatan laundry akan dilengkapi sesuai tarif pada tanggal masing-masing catatan."
                    data-confirm-label="Ya, Lengkapi Harga"
                    data-confirm-variant="gold"
                    class="flex justify-end gap-2 pt-1"
                >
                    @csrf
                    <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm font-medium text-ink hover:bg-sunken">
                        Batal
                    </button>
                    <button type="submit" class="min-h-11 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah">
                        Simpan Harga Otomatis
                    </button>
                </form>
            </div>
        </x-modal>
    @endif

    {{-- Modal Tetapkan Pemilik untuk Catatan Tanpa Nama --}}
    @if ($daftarPenghuniAktif->isNotEmpty() || $ringkasan['jumlah_tanpa_nama'] > 0)
        <x-modal id="modal-tetapkan-pemilik" judul="Tetapkan Pemilik Catatan">
            <form
                id="form-tetapkan-pemilik"
                method="POST"
                action=""
                data-confirm-title="Tetapkan Pemilik Catatan?"
                data-confirm-message="Catatan tanpa nama ini akan dipindahkan ke penghuni terpilih dan otomatis dihitung ke kuota bulanannya."
                data-confirm-label="Ya, Tetapkan"
                data-confirm-variant="maroon"
                class="space-y-4"
            >
                @csrf
                <p id="tetapkan-pemilik-ringkasan" class="rounded-xl border border-line bg-alt px-3.5 py-2.5 text-xs font-medium text-muted"></p>

                <x-field label="Pilih Penghuni Pemilik" name="penghuni_id" id="tetapkan-penghuni-id" :required="true">
                    <select
                        id="tetapkan-penghuni-id"
                        name="penghuni_id"
                        required
                        class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-base text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20 sm:text-sm"
                    >
                        <option value="">-- Pilih penghuni --</option>
                        @foreach ($daftarPenghuniAktif as $calon)
                            <option value="{{ $calon['id'] }}">{{ $calon['label'] }}</option>
                        @endforeach
                    </select>
                </x-field>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" data-modal-close class="min-h-11 rounded-xl border border-line-strong px-4 text-sm font-medium text-ink hover:bg-sunken">
                        Batal
                    </button>
                    <button type="submit" class="min-h-11 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah">
                        Simpan Pemilik
                    </button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
