<x-app-layout judul="Laporan Laundry" :subjudul="$laporan['nama_properti'] . ' · ' . $laporan['rentang']['label']">
    <x-slot:aksiHeader>
        <button
            type="button"
            data-modal-open="modal-rekap-wa"
            aria-label="Rekap WhatsApp"
            class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-ok px-2.5 text-xs font-bold text-white hover:bg-ok/90 sm:px-3.5 sm:text-sm"
        >
            <i class="fab fa-whatsapp text-sm" aria-hidden="true"></i>
            <span class="hidden sm:inline">Rekap WA</span>
        </button>

        <a
            href="{{ route('laporan.csv', request()->query()) }}"
            aria-label="Unduh CSV"
            class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-line-strong bg-surface px-2.5 text-xs font-bold text-ink hover:bg-sunken sm:px-3.5 sm:text-sm"
        >
            <i class="fas fa-file-csv text-xs text-maroon" aria-hidden="true"></i>
            <span class="hidden sm:inline">CSV</span>
        </a>

        <button
            type="button"
            data-print-laporan
            aria-label="Cetak atau simpan PDF"
            class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-gold px-2.5 text-xs font-bold text-white hover:bg-gold-bright sm:px-4 sm:text-sm"
        >
            <i class="fas fa-print text-xs" aria-hidden="true"></i>
            <span class="hidden sm:inline">Cetak / PDF</span>
        </button>
    </x-slot:aksiHeader>

    <div class="space-y-4">
        {{-- Panel Filter (Disembunyikan saat cetak) --}}
        <x-kartu class="print:hidden">
            <form
                method="GET"
                action="{{ route('laporan.index') }}"
                x-data="{ periode: @js($laporan['filter']['periode']) }"
                class="space-y-3"
            >
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="filter-periode" class="mb-1 block text-xs font-bold text-muted">Periode Laporan</label>
                        <select
                            id="filter-periode"
                            name="periode"
                            x-model="periode"
                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        >
                            <option value="bulan_ini">Bulan ini</option>
                            <option value="bulan_lalu">Bulan lalu</option>
                            <option value="rentang">Rentang tanggal</option>
                        </select>
                    </div>

                    <div x-show="periode === 'rentang'" x-cloak>
                        <label for="filter-dari" class="mb-1 block text-xs font-bold text-muted">Dari Tanggal</label>
                        <input
                            id="filter-dari"
                            type="date"
                            name="dari"
                            value="{{ $laporan['filter']['dari'] }}"
                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        >
                    </div>

                    <div x-show="periode === 'rentang'" x-cloak>
                        <label for="filter-sampai" class="mb-1 block text-xs font-bold text-muted">Sampai Tanggal</label>
                        <input
                            id="filter-sampai"
                            type="date"
                            name="sampai"
                            value="{{ $laporan['filter']['sampai'] }}"
                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        >
                    </div>

                    <div>
                        <label for="filter-penghuni" class="mb-1 block text-xs font-bold text-muted">Cetak Data</label>
                        <select
                            id="filter-penghuni"
                            name="penghuni_id"
                            class="block min-h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
                        >
                            <option value="">Semua penghuni / kamar</option>
                            @foreach ($daftarPenghuniFilter as $p)
                                @php
                                    $kamarP = $p->penempatanAktif?->kamar ?? $p->penempatan->first()?->kamar;
                                @endphp
                                <option value="{{ $p->id }}" @selected((int) ($laporan['filter']['penghuni_id'] ?? 0) === $p->id)>
                                    Kamar {{ $kamarP?->nomor ?? '-' }} — {{ $p->nama }}{{ $p->status === 'keluar' ? ' (Keluar)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            name="rinci"
                            value="1"
                            @checked($laporan['filter']['rinci'])
                            class="h-4 w-4 rounded border-line-strong text-maroon focus:ring-maroon"
                        >
                        <span>Mode rinci (tampilkan catatan entri)</span>
                    </label>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="submit"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-maroon px-5 text-sm font-bold text-white hover:bg-maroon-cerah"
                        >
                            <i class="fas fa-filter text-xs" aria-hidden="true"></i>
                            <span>Terapkan</span>
                        </button>

                        @if (request()->query() !== [])
                            <a
                                href="{{ route('laporan.index') }}"
                                class="inline-flex min-h-11 items-center rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken"
                            >
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </x-kartu>

        {{-- Lembar Laporan (Tampil di Layar & Tercetak Bersih A4) --}}
        <section class="lembar-laporan rounded-2xl border border-line bg-surface p-4 shadow-xs sm:p-6 print:rounded-none print:border-0 print:p-0 print:shadow-none">
            {{-- Header Laporan --}}
            <div class="flex flex-wrap items-start justify-between gap-4 border-b-[3px] border-maroon pb-3.5">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-ink sm:text-sm">{{ $laporan['nama_properti'] }}</p>
                    <h2 class="mt-0.5 text-xl font-bold tracking-tight text-ink sm:text-2xl">
                        {{ $laporan['rentang']['label'] }}
                    </h2>
                    <p class="mt-1 text-xs text-muted">Kuota gratis mengikuti periode laporan.</p>
                </div>

                <div class="text-right">
                    <p class="text-xs font-bold uppercase tracking-wider text-gold">LAPORAN LAUNDRY</p>
                    <p class="mt-1 text-xs text-muted">Dicetak {{ $laporan['dicetak_label'] }}</p>
                    <p class="text-xs text-muted">Kuota gratis {{ $laporan['kuota_gratis_label'] }} kg / penghuni</p>
                </div>
            </div>

            {{-- 3 Kotak Ringkasan --}}
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3 print:grid-cols-3">
                <div class="rounded-2xl border border-line bg-surface px-4 py-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted">TOTAL LAUNDRY</p>
                    <p class="mt-1 text-xl font-bold text-gold">{{ $laporan['ringkasan']['total_kg_label'] }} kg</p>
                </div>

                <div class="rounded-2xl border border-line bg-surface px-4 py-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted">JUMLAH PENGHUNI</p>
                    <p class="mt-1 text-xl font-bold text-gold">{{ $laporan['ringkasan']['jumlah_penghuni'] }}</p>
                </div>

                <div class="rounded-2xl border border-line bg-surface px-4 py-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted">MENDEKATI / LEWAT</p>
                    <p class="mt-1 text-xl font-bold text-danger">{{ $laporan['ringkasan']['jumlah_mendekati_lewat'] }}</p>
                </div>
            </div>

            {{-- Rincian Per Penghuni --}}
            <div class="mt-5">
                <h3 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-muted">RINCIAN PER PENGHUNI</h3>

                @if ($laporan['kartu_penghuni']->isEmpty() && $laporan['kartu_lainnya']->isEmpty())
                    <div class="rounded-2xl border border-dashed border-line-strong bg-alt px-4 py-10 text-center">
                        <p class="text-sm font-bold text-ink">Belum ada catatan laundry pada periode ini</p>
                        <p class="mt-1 text-xs text-muted">Coba pilih periode bulan lain atau tambahkan catatan laundry terlebih dahulu.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 print:grid-cols-2 print:gap-3">
                        @foreach ($laporan['kartu_penghuni'] as $kartu)
                            @php
                                $penghuni = $kartu['penghuni'];
                                $kuota = $kartu['kuota'];
                                $statusKuota = $kuota['status'] ?? 'normal';
                                $diBawahToleransi = (bool) ($kuota['di_bawah_toleransi'] ?? false);
                            @endphp
                            <article class="kartu-laporan flex flex-col rounded-2xl border border-line bg-surface p-4 break-inside-avoid print:p-3">
                                {{-- Header Kartu --}}
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-2.5">
                                        <span class="inline-flex shrink-0 items-center justify-center rounded-xl bg-maroon/10 px-2.5 py-1.5 text-xs font-bold text-maroon">
                                            {{ $kartu['label_kamar'] }}
                                        </span>

                                        <div class="min-w-0">
                                            <h4 class="text-sm font-bold text-ink">{{ $penghuni->nama }}</h4>

                                            @if ($penghuni->status === 'keluar')
                                                <span class="mt-0.5 inline-flex rounded-full bg-sunken px-2.5 py-0.5 text-[11px] font-medium text-muted">
                                                    Keluar
                                                </span>
                                            @endif

                                            <p class="mt-0.5 text-[11px] text-muted">
                                                {{ $kartu['jumlah_catatan'] }} catatan - Penghuni
                                            </p>

                                            <p class="mt-0.5 text-[11px] leading-snug text-ink/80">
                                                Kuota: {{ $kuota['terpakai_label'] }} / {{ $kuota['kuota_label'] }} kg kuota bulan ini
                                                @if ($kartu['tampilkan_periode_ini'])
                                                    - Periode ini {{ $kartu['total_kg_label'] }} kg
                                                @endif
                                                @if ((int) ($kuota['kelebihan_centi'] ?? 0) > 0)
                                                    - Lewat bulan ini {{ $kuota['kelebihan_label'] }} kg
                                                @endif
                                            </p>

                                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                                <x-lencana :status="$statusKuota" />
                                                @if ($diBawahToleransi)
                                                    <span class="inline-flex items-center rounded-full border border-danger/20 bg-danger/5 px-2 py-0.5 text-[10px] font-medium text-danger print:hidden">
                                                        Lewat · di bawah toleransi
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <span class="whitespace-nowrap text-base font-bold text-maroon sm:text-lg">
                                            {{ $kartu['total_kg_label'] }} kg
                                        </span>
                                    </div>
                                </div>

                                {{-- Daftar Catatan Bergaris Titik-Titik --}}
                                <div class="mt-3 rounded-xl bg-alt p-3 text-xs">
                                    <div class="space-y-1.5">
                                        @foreach ($kartu['baris'] as $baris)
                                            <div>
                                                <div class="flex items-baseline">
                                                    <span class="shrink-0 text-muted">
                                                        @if ($baris['tipe'] === 'item')
                                                            {{ $baris['tanggal_label'] }} - {{ $baris['nama_item'] }}
                                                        @else
                                                            {{ $baris['tanggal_label'] }}
                                                        @endif
                                                    </span>
                                                    <span class="mx-2 flex-1 border-b border-dotted border-line-strong" aria-hidden="true"></span>
                                                    <span class="shrink-0 font-bold {{ ! empty($baris['merah']) ? 'text-danger' : 'text-ink' }}">
                                                        @if ($baris['tipe'] === 'item')
                                                            1 item - Rp{{ number_format((int) $baris['biaya'], 0, ',', '.') }}
                                                        @else
                                                            {{ $baris['berat_label'] }} kg - {{ $baris['biaya'] !== null ? 'Rp'.number_format((int) $baris['biaya'], 0, ',', '.') : 'Belum diisi' }}
                                                        @endif
                                                    </span>
                                                </div>

                                                @if ($laporan['filter']['rinci'] && ! empty($baris['catatan']))
                                                    <p class="mt-0.5 pl-2 text-[11px] italic text-muted">
                                                        Catatan: {{ $baris['catatan'] }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-2.5 flex items-baseline border-t border-line pt-2 font-bold text-ink">
                                        <span class="shrink-0">Subtotal semua</span>
                                        <span class="mx-2 flex-1 border-b border-dotted border-line-strong" aria-hidden="true"></span>
                                        <span class="shrink-0">{{ $kartu['total_kg_label'] }} kg</span>
                                    </div>
                                </div>

                                {{-- Batang Ringkasan Biaya --}}
                                @php
                                    $bolehEditTagihan = auth()->user()->can('sesuaikanKuota', $penghuni);
                                    $punyaKelebihan = (int) ($kuota['kelebihan_centi'] ?? 0) > 0;
                                    $adaTagihanLewat = (int) $kartu['tagihan_lewat_kuota'] > 0;
                                    $sudahDisesuaikan = ($kuota['nominal_penyesuaian'] ?? null) !== null;
                                    $totalTagihanWa = (int) ($kartu['wa_tagihan']['total_tagihan'] ?? 0);
                                @endphp
                                <div class="mt-2.5 space-y-1.5 text-xs font-bold">
                                    <div class="flex items-center justify-between rounded-xl bg-ok/10 px-3 py-2 text-ok">
                                        <span>Biaya utama</span>
                                        <span>Rp{{ number_format((int) $kartu['biaya_utama'], 0, ',', '.') }}</span>
                                    </div>

                                    @if ($punyaKelebihan || $adaTagihanLewat)
                                        <div
                                            x-data="{ editTagihan: false }"
                                            class="{{ $adaTagihanLewat ? '' : 'print:hidden' }}"
                                        >
                                            {{-- Tampilan normal batang merah (klik untuk edit langsung bila punya izin) --}}
                                            <div
                                                x-show="!editTagihan"
                                                class="flex items-center justify-between gap-2 rounded-xl bg-danger/10 px-3 py-2 text-danger"
                                            >
                                                <span>
                                                    Tagihan lewat kuota {{ $kuota['kelebihan_label'] }} kg
                                                    @if ($sudahDisesuaikan)
                                                        <span class="font-medium print:hidden">(disesuaikan)</span>
                                                    @endif
                                                </span>
                                                <div class="flex items-center gap-2">
                                                    <span>Rp{{ number_format((int) $kartu['tagihan_lewat_kuota'], 0, ',', '.') }}</span>
                                                    @if ($bolehEditTagihan)
                                                        <button
                                                            type="button"
                                                            x-on:click="editTagihan = true"
                                                            aria-label="Ubah nominal tagihan lewat kuota {{ $penghuni->nama }}"
                                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-danger/15 text-danger hover:bg-danger hover:text-white print:hidden"
                                                        >
                                                            <i class="fas fa-pen text-[10px]" aria-hidden="true"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Mode edit inline persis aplikasi lama (tidak ikut tercetak) --}}
                                            @if ($bolehEditTagihan)
                                                <div
                                                    x-show="editTagihan"
                                                    x-cloak
                                                    class="space-y-2.5 rounded-xl border border-danger/25 bg-danger/10 p-3 text-danger print:hidden"
                                                >
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div class="flex items-center gap-2 text-xs font-bold">
                                                            <i class="fas fa-receipt text-sm" aria-hidden="true"></i>
                                                            <span>Tagihan lewat kuota {{ $kuota['kelebihan_label'] }} kg</span>
                                                        </div>
                                                        @if ($sudahDisesuaikan)
                                                            <form method="POST" action="{{ route('penghuni.penyesuaian-kuota', $penghuni) }}">
                                                                @csrf
                                                                <input type="hidden" name="bulan" value="{{ $laporan['rentang']['bulan_acuan'] }}">
                                                                <input type="hidden" name="reset" value="1">
                                                                <input type="hidden" name="kembali_ke" value="laporan">
                                                                <button type="submit" class="text-[11px] font-bold underline hover:text-maroon">
                                                                    Reset otomatis
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>

                                                    <form
                                                        method="POST"
                                                        action="{{ route('penghuni.penyesuaian-kuota', $penghuni) }}"
                                                        class="flex items-center gap-2"
                                                    >
                                                        @csrf
                                                        <input type="hidden" name="bulan" value="{{ $laporan['rentang']['bulan_acuan'] }}">
                                                        <input type="hidden" name="kembali_ke" value="laporan">
                                                        <span class="text-xs font-bold text-danger">Rp</span>
                                                        <input
                                                            type="number"
                                                            name="nominal"
                                                            min="0"
                                                            max="10000000"
                                                            step="100"
                                                            required
                                                            value="{{ (int) $kartu['tagihan_lewat_kuota'] }}"
                                                            aria-label="Nominal tagihan lewat kuota"
                                                            class="min-h-10 w-full flex-1 rounded-xl border border-danger/25 bg-surface px-3 text-sm font-bold text-danger focus:border-danger focus:outline-none focus:ring-2 focus:ring-danger/20"
                                                        >
                                                        <button
                                                            type="submit"
                                                            class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-danger px-4 text-xs font-bold text-white hover:bg-danger/90"
                                                        >
                                                            OK
                                                        </button>
                                                        <button
                                                            type="button"
                                                            x-on:click="editTagihan = false"
                                                            aria-label="Batal ubah tagihan"
                                                            class="inline-flex h-10 w-9 shrink-0 items-center justify-center rounded-xl text-danger hover:bg-danger/15"
                                                        >
                                                            <i class="fas fa-xmark text-sm" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if ((int) $kartu['bayar_terpisah'] > 0)
                                        <div class="flex items-center justify-between rounded-xl bg-gold-container px-3 py-2 text-on-gold">
                                            <span>Bayar terpisah {{ $kartu['jumlah_item_terpisah'] }} item</span>
                                            <span>Rp{{ number_format((int) $kartu['bayar_terpisah'], 0, ',', '.') }}</span>
                                        </div>
                                    @endif

                                    {{-- Tombol WhatsApp (Hanya muncul bila ada tagihan > 0, tidak ikut tercetak) --}}
                                    @if ($totalTagihanWa > 0)
                                        <div class="pt-1 print:hidden">
                                            @if (! empty($kartu['wa_tagihan']['url']))
                                                <a
                                                    href="{{ $kartu['wa_tagihan']['url'] }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-ok px-3 text-xs font-bold text-white transition-colors hover:bg-ok/90"
                                                >
                                                    <i class="fab fa-whatsapp text-sm" aria-hidden="true"></i>
                                                    <span>Kirim tagihan via WhatsApp</span>
                                                </a>
                                            @else
                                                <a
                                                    href="{{ route('penghuni.show', $penghuni) }}"
                                                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-line-strong bg-sunken/70 px-3 text-xs font-medium text-muted hover:bg-sunken"
                                                >
                                                    <i class="fab fa-whatsapp text-sm" aria-hidden="true"></i>
                                                    <span>Nomor WhatsApp belum diisi — lengkapi di profil</span>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach

                        {{-- Kartu Tambahan (Pemilik Kos / Belum Ada Pemilik) --}}
                        @foreach ($laporan['kartu_lainnya'] as $kartuLain)
                            <article class="kartu-laporan flex flex-col rounded-2xl border border-line bg-surface p-4 break-inside-avoid print:p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-bold text-ink">{{ $kartuLain['judul'] }}</h4>
                                        <p class="mt-0.5 text-[11px] text-muted">{{ $kartuLain['jumlah_catatan'] }} catatan</p>
                                    </div>
                                    <span class="whitespace-nowrap text-base font-bold text-maroon sm:text-lg">
                                        {{ $kartuLain['total_kg_label'] }} kg
                                    </span>
                                </div>

                                <div class="mt-3 rounded-xl bg-alt p-3 text-xs">
                                    <div class="space-y-1.5">
                                        @foreach ($kartuLain['baris'] as $baris)
                                            <div class="flex items-baseline">
                                                <span class="shrink-0 text-muted">
                                                    @if ($baris['tipe'] === 'item')
                                                        {{ $baris['tanggal_label'] }} - {{ $baris['nama_item'] }}
                                                    @else
                                                        {{ $baris['tanggal_label'] }}
                                                    @endif
                                                </span>
                                                <span class="mx-2 flex-1 border-b border-dotted border-line-strong" aria-hidden="true"></span>
                                                <span class="shrink-0 font-bold text-ink">
                                                    @if ($baris['tipe'] === 'item')
                                                        1 item - Rp{{ number_format((int) $baris['biaya'], 0, ',', '.') }}
                                                    @else
                                                        {{ $baris['berat_label'] }} kg - {{ $baris['biaya'] !== null ? 'Rp'.number_format((int) $baris['biaya'], 0, ',', '.') : 'Belum diisi' }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-2.5 flex items-baseline border-t border-line pt-2 font-bold text-ink">
                                        <span class="shrink-0">Subtotal semua</span>
                                        <span class="mx-2 flex-1 border-b border-dotted border-line-strong" aria-hidden="true"></span>
                                        <span class="shrink-0">{{ $kartuLain['total_kg_label'] }} kg</span>
                                    </div>
                                </div>

                                <div class="mt-2.5 space-y-1.5 text-xs font-bold">
                                    <div class="flex items-center justify-between rounded-xl bg-ok/10 px-3 py-2 text-ok">
                                        <span>Biaya utama</span>
                                        <span>Rp{{ number_format((int) $kartuLain['biaya_utama'], 0, ',', '.') }}</span>
                                    </div>

                                    @if ((int) $kartuLain['bayar_terpisah'] > 0)
                                        <div class="flex items-center justify-between rounded-xl bg-gold-container px-3 py-2 text-on-gold">
                                            <span>Bayar terpisah {{ $kartuLain['jumlah_item_terpisah'] }} item</span>
                                            <span>Rp{{ number_format((int) $kartuLain['bayar_terpisah'], 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Footer Total Keseluruhan Marun --}}
            <footer class="mt-6 overflow-hidden rounded-2xl break-inside-avoid">
                <div class="flex flex-wrap items-center justify-between gap-3 bg-maroon px-5 py-4 text-white">
                    <div>
                        <p class="text-sm font-bold sm:text-base">Total keseluruhan laundry</p>
                        <p class="mt-0.5 text-xs text-white/85">
                            Tagihan lewat kuota Rp{{ number_format((int) $laporan['ringkasan']['total_tagihan_lewat'], 0, ',', '.') }}
                            · Biaya utama Rp{{ number_format((int) $laporan['ringkasan']['total_biaya_utama'], 0, ',', '.') }}
                            · Bayar terpisah Rp{{ number_format((int) $laporan['ringkasan']['total_bayar_terpisah'], 0, ',', '.') }}
                        </p>
                    </div>
                    <p class="text-2xl font-bold">{{ $laporan['ringkasan']['total_kg_label'] }} kg</p>
                </div>
                <div class="bg-sunken/70 px-5 py-3 text-center text-xs text-muted">
                    Entri mengikuti filter tanggal. Kuota gratis dihitung dari total bulan terkait.
                </div>
            </footer>
        </section>

        {{-- Tombol Emas Cetak / Simpan PDF di Bawah Layar (Tidak Ikut Tercetak) --}}
        <div class="sticky bottom-20 z-20 flex justify-center print:hidden lg:bottom-6">
            <button
                type="button"
                data-print-laporan
                class="inline-flex min-h-12 items-center gap-2 rounded-full bg-gold px-6 text-sm font-bold text-white shadow-lg transition-colors hover:bg-gold-bright"
            >
                <i class="fas fa-print text-sm" aria-hidden="true"></i>
                <span>Cetak / Simpan PDF</span>
            </button>
        </div>
    </div>

    {{-- Modal Rekap WhatsApp Seluruh Gedung --}}
    <x-modal id="modal-rekap-wa" judul="Rekap Laundry Seluruh Gedung (WhatsApp)" lebar="max-w-2xl">
        <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                <span>Panjang teks: <strong class="text-ink">{{ number_format($laporan['rekap_wa']['jumlah_karakter'], 0, ',', '.') }} karakter</strong></span>
                @if ($laporan['rekap_wa']['terlalu_panjang'])
                    <span class="rounded-full bg-gold-container px-2.5 py-0.5 font-medium text-on-gold">
                        Terlalu panjang untuk tautan wa.me — gunakan Salin Teks / Bagikan
                    </span>
                @endif
            </div>

            <label for="rekap-wa-teks" class="sr-only">Teks rekap WhatsApp seluruh gedung</label>
            <textarea
                id="rekap-wa-teks"
                readonly
                rows="14"
                class="w-full rounded-xl border border-line-strong bg-alt p-3 font-mono text-xs leading-relaxed text-ink focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20"
            >{{ $laporan['rekap_wa']['pesan'] }}</textarea>

            <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                <span class="copy-wrap relative inline-flex items-center gap-2">
                    <button
                        type="button"
                        command="--copy"
                        commandfor="rekap-wa-teks"
                        class="copy-button inline-flex min-h-11 items-center gap-2 rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah"
                    >
                        <i class="fas fa-copy text-xs" aria-hidden="true"></i>
                        <span>Salin Teks</span>
                    </button>
                    <span class="copy-tip text-xs font-bold text-ok" role="status"></span>
                </span>

                <button
                    type="button"
                    data-share-target="rekap-wa-teks"
                    data-share-title="Rekap Laundry {{ $laporan['nama_properti'] }} ({{ $laporan['rentang']['label'] }})"
                    class="hidden min-h-11 items-center gap-2 rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken"
                >
                    <i class="fas fa-share-nodes text-xs" aria-hidden="true"></i>
                    <span>Bagikan</span>
                </button>

                @if (! empty($laporan['rekap_wa']['url']))
                    <a
                        href="{{ $laporan['rekap_wa']['url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-ok px-4 text-sm font-bold text-white hover:bg-ok/90"
                    >
                        <i class="fab fa-whatsapp text-sm" aria-hidden="true"></i>
                        <span>Buka WhatsApp</span>
                    </a>
                @endif

                <button
                    type="button"
                    data-modal-close
                    class="min-h-11 rounded-xl border border-line-strong px-4 text-sm text-ink hover:bg-sunken"
                >
                    Tutup
                </button>
            </div>
        </div>
    </x-modal>
</x-app-layout>
