import '@fontsource/roboto/400.css';
import '@fontsource/roboto/500.css';
import '@fontsource/roboto/700.css';
import Alpine from 'alpinejs';

const DRAFT_PREFIX = 'laundry_kos_draf:';

document.addEventListener('alpine:init', () => {
    Alpine.data('appShell', () => ({
        drawerOpen: false,
        toggleDrawer() {
            this.drawerOpen = !this.drawerOpen;
            this.syncScrollLock();
        },
        closeDrawer() {
            this.drawerOpen = false;
            this.syncScrollLock();
        },
        syncScrollLock() {
            document.documentElement.classList.toggle('overflow-hidden', this.drawerOpen);
        },
    }));

    Alpine.data('dropdownMenu', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    }));

    Alpine.data('passwordField', () => ({
        show: false,
        toggle() {
            this.show = !this.show;
        },
    }));

    Alpine.data('modalDialog', (id, closedby = 'any') => ({
        id,
        closedby,
        buka(targetId) {
            if (targetId === this.id) {
                this.$el.showModal();
            }
        },
        tutup(targetId) {
            if (targetId === this.id && this.$el.open) {
                this.$el.close();
            }
        },
        klikLuar(event) {
            if (event.target === this.$el && this.closedby === 'any') {
                if (window.__cobaTutupModalDenganCekDraf && window.__cobaTutupModalDenganCekDraf(this.$el)) {
                    return;
                }
                this.$el.close();
            }
        },
    }));

    Alpine.data('toastBar', (initialTipe = 'ok', initialPesan = '', initialAksiUrl = '', initialAksiLabel = '') => ({
        tampil: Boolean(initialPesan),
        tipe: initialTipe,
        pesan: initialPesan,
        aksiUrl: initialAksiUrl,
        aksiLabel: initialAksiLabel,
        timer: null,
        init() {
            if (this.tampil) {
                this.umumkan();
                this.jadwalkanTutup();
            }
        },
        mulai(detail) {
            this.tipe = detail?.tipe ?? 'ok';
            this.pesan = detail?.pesan ?? '';
            this.aksiUrl = detail?.aksi_url ?? '';
            this.aksiLabel = detail?.aksi_label ?? '';
            this.tampil = Boolean(this.pesan);
            if (this.tampil) {
                this.umumkan();
                this.jadwalkanTutup();
            }
        },
        umumkan() {
            if (typeof document.ariaNotify === 'function' && this.pesan) {
                document.ariaNotify(this.pesan + (this.aksiUrl ? '. Aksi tersedia.' : ''));
            }
        },
        jeda() {
            clearTimeout(this.timer);
        },
        lanjutkan() {
            if (this.tampil) {
                this.jadwalkanTutup();
            }
        },
        tutup() {
            clearTimeout(this.timer);
            this.tampil = false;
        },
        jadwalkanTutup() {
            clearTimeout(this.timer);
            const durasi = this.aksiUrl ? 8000 : 5000;
            this.timer = setTimeout(() => {
                this.tampil = false;
            }, durasi);
        },
    }));

    Alpine.data('wizardKamar', () => ({
        baris: [{ lantai: 1, dari: 101, sampai: 109 }],
        tambah() {
            const lantaiBerikut = this.baris.length + 1;
            const awal = lantaiBerikut * 100 + 1;
            this.baris.push({ lantai: lantaiBerikut, dari: awal, sampai: awal + 9 });
        },
        hapus(idx) {
            if (this.baris.length > 1) {
                this.baris.splice(idx, 1);
            }
        },
    }));

    Alpine.data('kontrakSewa', (awal = {}) => ({
        status: awal.status ?? 'aktif',
        tanggalMasuk: awal.tanggalMasuk ?? '',
        jangkaWaktu: awal.jangkaWaktu ?? 'tidak_tentu',
        tanggalKeluarRencana: awal.tanggalKeluarRencana ?? '',
        tanggalKeluar: awal.tanggalKeluar ?? '',
        saatUbahJangkaWaktu() {
            if (this.jangkaWaktu === 'tidak_tentu') {
                this.tanggalKeluarRencana = '';
                return;
            }
            this.hitungRencanaOtomatis();
        },
        saatUbahTanggalMasuk() {
            if (this.jangkaWaktu !== 'tidak_tentu') {
                this.hitungRencanaOtomatis();
            }
        },
        hitungRencanaOtomatis() {
            const tambahBulanMap = {
                bulanan: 1,
                '3_bulan': 3,
                '6_bulan': 6,
                '1_tahun': 12,
            };
            const tambah = tambahBulanMap[this.jangkaWaktu] ?? 0;
            if (!tambah || !/^\d{4}-\d{2}-\d{2}$/.test(this.tanggalMasuk)) {
                return;
            }
            const [y, m, d] = this.tanggalMasuk.split('-').map(Number);
            const totalBulan = y * 12 + (m - 1) + tambah;
            const targetTahun = Math.floor(totalBulan / 12);
            const targetBulan = totalBulan % 12;
            const akhirBulan = new Date(Date.UTC(targetTahun, targetBulan + 1, 0)).getUTCDate();
            const targetHari = Math.min(d, akhirBulan);
            const mm = String(targetBulan + 1).padStart(2, '0');
            const dd = String(targetHari).padStart(2, '0');
            this.tanggalKeluarRencana = `${targetTahun}-${mm}-${dd}`;
        },
    }));

    Alpine.data('formLaundry', (awal = {}) => ({
        jenis: awal.jenis ?? 'penghuni',
        tanggal: awal.tanggal ?? '',
        penghuniId: awal.penghuniId ? String(awal.penghuniId) : '',
        teksCombobox: '',
        sedangMengetikCombobox: false,
        comboboxTerbuka: false,
        indeksSorot: -1,
        daftarPenghuni: Array.isArray(awal.daftarPenghuni) ? awal.daftarPenghuni : [],
        beratKg: awal.beratKg ?? '',
        biayaUtama: awal.biayaUtama ?? '',
        biayaManual: Boolean(awal.biayaManual),
        biayaOtomatis: awal.biayaOtomatis ?? null,
        tarifPerKg: Number(awal.tarifPerKg ?? 8000),
        minKgCenti: Number(awal.minKgCenti ?? 200),
        konfirmasiBeratBesar: Boolean(awal.konfirmasiBeratBesar),
        maksItem: Number(awal.maksItem ?? 10),
        nextKey: 1,
        item: [],
        infoKuota: null,
        kamarTerdeteksi: awal.kamarAwal ?? null,
        galatTanggal: null,
        duplikatCatatan: null,
        memuatInfo: false,
        endpointInfo: awal.endpointInfo ?? '',
        kecualiId: awal.kecualiId ?? null,
        debounceTimer: null,
        abortCtrl: null,
        init() {
            const awalItem = Array.isArray(awal.item) ? awal.item : [];
            this.item = awalItem.map((baris) => this.buatBarisItem(baris));
            this.sinkronTeksCombobox();
            this.hitungUlangOtomatis(false);
            if (this.endpointInfo) {
                this.muatInfoKuota(false);
            }
        },
        sinkronTeksCombobox() {
            const terpilih = this.daftarPenghuni.find((p) => String(p.id) === String(this.penghuniId));
            this.teksCombobox = terpilih ? String(terpilih.label ?? '') : '';
            this.sedangMengetikCombobox = false;
        },
        bukaCombobox() {
            this.comboboxTerbuka = true;
            this.indeksSorot = this.penghuniTersaring.findIndex((p) => String(p.id) === String(this.penghuniId));
        },
        toggleCombobox() {
            if (this.comboboxTerbuka) {
                this.tutupCombobox();
            } else {
                this.sedangMengetikCombobox = false;
                this.bukaCombobox();
                this.$el.querySelector('#laundry-penghuni-input')?.focus();
            }
        },
        tutupCombobox() {
            this.comboboxTerbuka = false;
            this.indeksSorot = -1;
            if (this.penghuniId) {
                this.sinkronTeksCombobox();
            } else if (!this.sedangMengetikCombobox) {
                this.teksCombobox = '';
            }
        },
        saatKetikCombobox() {
            this.sedangMengetikCombobox = true;
            this.comboboxTerbuka = true;
            this.indeksSorot = 0;
            const cocokPersis = this.daftarPenghuni.find(
                (p) => String(p.label ?? '').toLowerCase() === this.teksCombobox.trim().toLowerCase()
            );
            if (cocokPersis) {
                this.penghuniId = String(cocokPersis.id);
            } else if (this.penghuniId !== '') {
                this.penghuniId = '';
                this.infoKuota = null;
                this.galatTanggal = null;
                this.duplikatCatatan = null;
            }
        },
        pilihPenghuni(calon) {
            this.penghuniId = String(calon.id);
            this.teksCombobox = String(calon.label ?? '');
            this.sedangMengetikCombobox = false;
            this.comboboxTerbuka = false;
            this.indeksSorot = -1;
            this.saatUbahPenghuni();
        },
        bersihkanPenghuni() {
            this.penghuniId = '';
            this.teksCombobox = '';
            this.sedangMengetikCombobox = false;
            this.infoKuota = null;
            this.galatTanggal = null;
            this.duplikatCatatan = null;
            this.bukaCombobox();
            this.$el.querySelector('#laundry-penghuni-input')?.focus();
        },
        sorotBerikut() {
            if (!this.comboboxTerbuka) {
                this.bukaCombobox();
                return;
            }
            const total = this.penghuniTersaring.length;
            if (total === 0) {
                return;
            }
            this.indeksSorot = (this.indeksSorot + 1) % total;
        },
        sorotSebelum() {
            if (!this.comboboxTerbuka) {
                this.bukaCombobox();
                return;
            }
            const total = this.penghuniTersaring.length;
            if (total === 0) {
                return;
            }
            this.indeksSorot = this.indeksSorot <= 0 ? total - 1 : this.indeksSorot - 1;
        },
        pilihYangDisorot() {
            const daftar = this.penghuniTersaring;
            if (this.comboboxTerbuka && daftar.length > 0) {
                const idx = this.indeksSorot >= 0 && this.indeksSorot < daftar.length ? this.indeksSorot : 0;
                this.pilihPenghuni(daftar[idx]);
            }
        },
        buatBarisItem(baris = {}) {
            return {
                _key: this.nextKey++,
                nama: String(baris.nama ?? ''),
                jumlah: Number(baris.jumlah ?? 1) || 1,
                berat_kg: String(baris.berat_kg ?? ''),
                biaya: baris.biaya !== undefined && baris.biaya !== null ? String(baris.biaya) : '',
            };
        },
        keCentiKg(str) {
            if (str === null || str === undefined) {
                return 0;
            }
            const bersih = String(str).replace(',', '.').trim();
            const match = bersih.match(/^(\d+)(?:\.(\d+))?$/);
            if (!match) {
                return 0;
            }
            const bulat = Number(match[1]);
            const pecahan = (match[2] ?? '').padEnd(3, '0');
            let centi = bulat * 100 + Number(pecahan.slice(0, 2));
            if (Number(pecahan[2]) >= 5) {
                centi += 1;
            }
            return centi;
        },
        hitungBiayaOtomatisNilai(beratCenti) {
            if (beratCenti <= 0) {
                return null;
            }
            const beratDihitung = Math.max(beratCenti, this.minKgCenti);
            return Math.floor((beratDihitung * this.tarifPerKg + 50) / 100);
        },
        hitungUlangOtomatis(isiBiaya = true) {
            const beratCenti = this.keCentiKg(this.beratKg);
            this.biayaOtomatis = this.hitungBiayaOtomatisNilai(beratCenti);
            if (!isiBiaya) {
                return;
            }
            if (beratCenti <= 0) {
                if (!this.biayaManual) {
                    this.biayaUtama = '';
                }
                return;
            }
            if (!this.biayaManual || this.biayaUtama.trim() === '') {
                this.biayaUtama = String(this.biayaOtomatis ?? '');
            }
        },
        saatUbahJenis() {
            if (this.jenis !== 'penghuni') {
                this.galatTanggal = null;
                this.duplikatCatatan = null;
                this.infoKuota = null;
                this.comboboxTerbuka = false;
            } else {
                this.muatInfoKuota(false);
            }
        },
        saatUbahBerat() {
            this.hitungUlangOtomatis(true);
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.muatInfoKuota(false), 250);
        },
        saatUbahBiayaManual() {
            const teks = String(this.biayaUtama ?? '').trim();
            if (teks === '') {
                this.biayaManual = false;
                return;
            }
            this.biayaManual = this.biayaOtomatis === null || Number(teks) !== Number(this.biayaOtomatis);
        },
        kembalikanOtomatis() {
            this.biayaManual = false;
            this.biayaUtama = this.biayaOtomatis !== null ? String(this.biayaOtomatis) : '';
        },
        saatUbahTanggal() {
            this.muatInfoKuota(true);
        },
        saatUbahPenghuni() {
            this.muatInfoKuota(false);
        },
        async muatInfoKuota(perbaruiPenghuni = false) {
            if (!this.endpointInfo || !/^\d{4}-\d{2}-\d{2}$/.test(this.tanggal)) {
                return;
            }
            if (this.abortCtrl) {
                this.abortCtrl.abort();
            }
            this.abortCtrl = new AbortController();
            this.memuatInfo = true;

            const params = new URLSearchParams({ tanggal: this.tanggal });
            if (this.jenis === 'penghuni' && this.penghuniId) {
                params.set('penghuni_id', this.penghuniId);
            }
            const beratBersih = String(this.beratKg ?? '').trim();
            if (beratBersih !== '') {
                params.set('berat_kg', beratBersih);
            }
            if (this.kecualiId) {
                params.set('kecuali_id', String(this.kecualiId));
            }

            try {
                const res = await fetch(`${this.endpointInfo}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                    signal: this.abortCtrl.signal,
                });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                this.tarifPerKg = Number(data.tarif_per_kg ?? this.tarifPerKg);
                this.minKgCenti = Number(data.min_kg_centi ?? this.minKgCenti);
                this.hitungUlangOtomatis(true);
                this.kamarTerdeteksi = data.kamar ?? null;
                this.infoKuota = data.kuota ?? null;
                this.galatTanggal = data.galat ?? null;
                this.duplikatCatatan = data.duplikat ?? null;
                if (perbaruiPenghuni && Array.isArray(data.daftar_penghuni)) {
                    this.daftarPenghuni = data.daftar_penghuni;
                    if (this.penghuniId && !this.sedangMengetikCombobox) {
                        this.sinkronTeksCombobox();
                    }
                }
            } catch (err) {
                if (err?.name !== 'AbortError') {
                    // Abaikan gangguan jaringan sesaat saat pratinjau live
                }
            } finally {
                this.memuatInfo = false;
            }
        },
        tambahItem(namaAwal = '') {
            if (this.item.length >= this.maksItem) {
                return;
            }
            this.item.push(
                this.buatBarisItem({
                    nama: namaAwal === 'Lainnya' ? '' : namaAwal,
                    jumlah: 1,
                    berat_kg: '',
                    biaya: '',
                })
            );
        },
        hapusItem(idx) {
            this.item.splice(idx, 1);
        },
        pulihkanDraf(data) {
            if (!data || typeof data !== 'object') {
                return;
            }
            if ('jenis' in data && data.jenis) {
                this.jenis = String(data.jenis);
            }
            if ('tanggal' in data) {
                this.tanggal = String(data.tanggal ?? '');
            }
            if ('penghuni_id' in data) {
                this.penghuniId = data.penghuni_id ? String(data.penghuni_id) : '';
                this.sinkronTeksCombobox();
            }
            if ('berat_kg' in data) {
                this.beratKg = String(data.berat_kg ?? '');
            }
            if ('biaya_utama' in data) {
                this.biayaUtama = String(data.biaya_utama ?? '');
            }
            if ('konfirmasi_berat_besar' in data) {
                this.konfirmasiBeratBesar = Boolean(data.konfirmasi_berat_besar);
            }

            const barisMap = new Map();
            for (const [kunci, nilai] of Object.entries(data)) {
                const match = kunci.match(/^item\[(\d+)\]\[(nama|jumlah|berat_kg|biaya)\]$/);
                if (!match) {
                    continue;
                }
                const idx = Number(match[1]);
                const kolom = match[2];
                if (!barisMap.has(idx)) {
                    barisMap.set(idx, {});
                }
                barisMap.get(idx)[kolom] = nilai;
            }
            const terurut = Array.from(barisMap.keys())
                .sort((a, b) => a - b)
                .map((idx) => this.buatBarisItem(barisMap.get(idx)));
            this.item = terurut;
            this.hitungUlangOtomatis(false);
            this.saatUbahBiayaManual();
            if (this.endpointInfo) {
                this.muatInfoKuota(true);
            }
        },
        get penghuniTersaring() {
            if (!this.sedangMengetikCombobox) {
                return this.daftarPenghuni;
            }
            const kata = this.teksCombobox.trim().toLowerCase();
            if (kata === '') {
                return this.daftarPenghuni;
            }
            return this.daftarPenghuni.filter((p) => String(p.label ?? '').toLowerCase().includes(kata));
        },
        get butuhKonfirmasiBerat() {
            return this.keCentiKg(this.beratKg) > 3000;
        },
        get totalTerpisah() {
            return this.item.reduce((sum, baris) => {
                const angka = Number.parseInt(String(baris.biaya ?? '').trim(), 10);
                return sum + (Number.isFinite(angka) && angka > 0 ? angka : 0);
            }, 0);
        },
        get totalJumlahItem() {
            return this.item.reduce((sum, baris) => {
                const angka = Number.parseInt(String(baris.jumlah ?? '1'), 10);
                return sum + (Number.isFinite(angka) && angka > 0 ? angka : 1);
            }, 0);
        },
        get teksBantuKuota() {
            if (this.jenis !== 'penghuni') {
                return this.jenis === 'gedung'
                    ? 'Cucian internal gedung tidak mengurangi kuota penghuni manapun.'
                    : 'Cucian tanpa nama tidak mengurangi kuota penghuni sampai pemiliknya ditetapkan.';
            }
            if (!this.penghuniId) {
                return 'Pilih penghuni untuk melihat pemakaian dan sisa kuota bulanan.';
            }
            if (this.galatTanggal) {
                return this.galatTanggal;
            }
            if (!this.infoKuota) {
                return this.memuatInfo ? 'Memuat status kuota...' : '';
            }
            if (this.infoKuota.mode_kuota === 'tanpa_kuota') {
                return `Mode tanpa kuota aktif pada ${this.infoKuota.label_bulan}.`;
            }
            const prefix = this.kamarTerdeteksi ? `Kamar ${this.kamarTerdeteksi} · ` : '';
            const beratAda = this.keCentiKg(this.beratKg) > 0;
            if (!beratAda) {
                return `${prefix}Pemakaian ${this.infoKuota.label_bulan}: ${this.infoKuota.sebelum_kg}/${this.infoKuota.kuota_kg} kg (sisa ${this.infoKuota.sisa_kg} kg).`;
            }
            if (this.infoKuota.status === 'lewat') {
                return `${prefix}Kuota ${this.infoKuota.label_bulan}: ${this.infoKuota.sebelum_kg}/${this.infoKuota.kuota_kg} kg → ${this.infoKuota.sesudah_kg} kg (lewat ${this.infoKuota.lewat_kg} kg).`;
            }
            return `${prefix}Kuota ${this.infoKuota.label_bulan}: ${this.infoKuota.sebelum_kg}/${this.infoKuota.kuota_kg} kg → ${this.infoKuota.sesudah_kg} kg (sisa ${this.infoKuota.sisa_kg} kg).`;
        },
        formatRupiah(n) {
            return new Intl.NumberFormat('id-ID').format(Number(n || 0));
        },
    }));

    Alpine.data('editorTemplateWa', (awal = {}) => ({
        aktif: 'tagihan',
        tagihan: String(awal.tagihan ?? ''),
        peringatan: String(awal.peringatan ?? ''),
        rekap: String(awal.rekap ?? ''),
        gedung: String(awal.gedung ?? "D'bata Residence"),
        sisipkan(variabel) {
            const el = document.getElementById(`wa-${this.aktif}`);
            if (!el) {
                this[this.aktif] = `${this[this.aktif]}${variabel}`;
                return;
            }
            const start = el.selectionStart ?? this[this.aktif].length;
            const end = el.selectionEnd ?? start;
            const teks = this[this.aktif];
            this[this.aktif] = teks.slice(0, start) + variabel + teks.slice(end);
            this.$nextTick(() => {
                el.focus();
                const pos = start + variabel.length;
                el.setSelectionRange(pos, pos);
            });
        },
        pratinjau(kode) {
            const contoh = {
                '{nama}': 'Glenn',
                '{kamar}': '302',
                '{gedung}': this.gedung,
                '{periode}': '1 Nov 2026 - 30 Nov 2026',
                '{rincian}': '• 10 Nov 2026: 2,8 kg - Rp22.400\n• 10 Nov 2026 (Selimut): 1 item - Rp15.000',
                '{kuota_terpakai}': '23,7',
                '{kuota}': '20,0',
                '{sisa}': '0,0',
                '{lewat_kg}': '3,7',
                '{tagihan_lewat}': 'Tagihan lewat kuota 3,7 kg: Rp24.000\nBayar terpisah (1 item): Rp15.000',
                '{terpisah}': 'Rp15.000',
                '{total}': 'Rp39.000',
            };
            let hasil = String(this[kode] ?? '');
            for (const [k, v] of Object.entries(contoh)) {
                hasil = hasil.split(k).join(v);
            }
            return hasil.replace(/\{[a-z0-9_]+\}/gi, '').replace(/\n{3,}/g, '\n\n').trim();
        },
    }));
});

document.addEventListener('click', (event) => {
    const openTrigger = event.target.closest('[data-modal-open]');
    if (openTrigger) {
        const target = document.getElementById(openTrigger.getAttribute('data-modal-open'));
        if (target && typeof target.showModal === 'function') {
            target.showModal();
        }
        return;
    }

    const tetapkanTrigger = event.target.closest('[data-tetapkan-action]');
    if (tetapkanTrigger) {
        const formTetapkan = document.getElementById('form-tetapkan-pemilik');
        const ringkasanEl = document.getElementById('tetapkan-pemilik-ringkasan');
        const dialogTetapkan = document.getElementById('modal-tetapkan-pemilik');
        if (formTetapkan && dialogTetapkan) {
            formTetapkan.action = tetapkanTrigger.getAttribute('data-tetapkan-action') || '';
            if (ringkasanEl) {
                ringkasanEl.textContent = tetapkanTrigger.getAttribute('data-tetapkan-ringkasan') || '';
            }
            dialogTetapkan.showModal();
        }
        return;
    }

    const closeTrigger = event.target.closest('[data-modal-close]');
    if (closeTrigger) {
        const dialog = closeTrigger.closest('dialog');
        if (dialog && typeof dialog.close === 'function') {
            if (window.__cobaTutupModalDenganCekDraf && window.__cobaTutupModalDenganCekDraf(dialog)) {
                event.preventDefault();
                return;
            }
            dialog.close();
        }
    }
});

function initInlineValidation() {
    const seluruhForm = Array.from(document.querySelectorAll('form[method="POST"], form[method="post"]'));
    for (const form of seluruhForm) {
        form.setAttribute('novalidate', 'novalidate');
    }

    const ambilLabelField = (el) => {
        if (el.dataset.fieldLabel) {
            return el.dataset.fieldLabel.trim();
        }
        const wrapper = el.closest('[data-field-wrapper]');
        if (wrapper?.dataset.fieldLabel) {
            return wrapper.dataset.fieldLabel.trim();
        }
        if (el.id) {
            const labelEl = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
            if (labelEl) {
                return labelEl.textContent.replace(/\*/g, '').trim();
            }
        }
        return el.name || 'Isian ini';
    };

    const cariElemenError = (el) => {
        const wrapper = el.closest('[data-field-wrapper]');
        if (wrapper) {
            const p = wrapper.querySelector('[data-field-error]');
            if (p) {
                return p;
            }
        }
        if (el.id) {
            const byId = document.getElementById(`${el.id}-error`);
            if (byId) {
                return byId;
            }
        }
        if (el.parentElement) {
            return el.parentElement.querySelector('[data-client-field-error]');
        }
        return null;
    };

    const bersihkanErrorField = (el) => {
        if (!el || typeof el.setCustomValidity !== 'function') {
            return;
        }
        el.setCustomValidity('');
        el.setAttribute('aria-invalid', 'false');
        el.classList.remove('border-danger');
        if (!el.classList.contains('border-line-strong')) {
            el.classList.add('border-line-strong');
        }

        const errorEl = cariElemenError(el);
        if (errorEl) {
            if (errorEl.hasAttribute('data-client-field-error')) {
                errorEl.remove();
            } else {
                errorEl.textContent = '';
                errorEl.classList.add('hidden');
            }
        }
    };

    const tampilkanErrorField = (el, pesan) => {
        el.setCustomValidity(pesan);
        el.setAttribute('aria-invalid', 'true');
        el.classList.remove('border-line-strong');
        el.classList.add('border-danger');

        let errorEl = cariElemenError(el);
        if (!errorEl && el.parentElement) {
            errorEl = document.createElement('p');
            errorEl.setAttribute('data-client-field-error', 'true');
            errorEl.className = 'mt-1 text-xs font-medium text-danger';
            el.parentElement.appendChild(errorEl);
        }
        if (errorEl) {
            errorEl.textContent = pesan;
            errorEl.classList.remove('hidden');
        }
    };

    const pesanConstraintHtml = (el) => {
        const v = el.validity;
        const label = ambilLabelField(el);

        if (v.valueMissing) {
            if (el.tagName === 'SELECT') {
                return `${label} belum dipilih.`;
            }
            if (el.type === 'checkbox') {
                return `${label} wajib dicentang.`;
            }
            return `${label} belum diisi.`;
        }
        if (v.badInput) {
            return `${label} harus berupa angka yang valid.`;
        }
        if (v.typeMismatch) {
            if (el.type === 'email') {
                return `Format alamat email pada ${label} tidak valid.`;
            }
            return `Format ${label} tidak valid.`;
        }
        if (v.tooShort) {
            return `${label} minimal ${el.minLength} karakter.`;
        }
        if (v.tooLong) {
            return `${label} maksimal ${el.maxLength} karakter.`;
        }
        if (v.rangeUnderflow) {
            return `Nilai ${label} minimal ${el.min}.`;
        }
        if (v.rangeOverflow) {
            return `Nilai ${label} maksimal ${el.max}.`;
        }
        if (v.stepMismatch) {
            return `Nilai ${label} harus kelipatan ${el.step}.`;
        }
        if (v.patternMismatch) {
            return el.dataset.patternMessage || `Format ${label} tidak sesuai.`;
        }
        return '';
    };

    const validasiAturanKhusus = (form, setError) => {
        const byName = (name) => Array.from(form.elements).find((el) => el.name === name);

        // 1. Wizard Setup: jika salah satu kolom staf diisi, wajib lengkapi name + username + password
        if (form.id === 'form-setup-semua') {
            const nameEl = byName('name');
            const usernameEl = byName('username');
            const passwordEl = byName('password');

            const nameVal = (nameEl?.value || '').trim();
            const usernameVal = (usernameEl?.value || '').trim();
            const passwordVal = passwordEl?.value || '';

            if (nameVal !== '' || usernameVal !== '' || passwordVal !== '') {
                if (nameEl && nameVal === '') {
                    setError(nameEl, 'Nama karyawan belum diisi (wajib dilengkapi jika ingin menambah staf baru).');
                }
                if (usernameEl) {
                    if (usernameVal === '') {
                        setError(usernameEl, 'Username karyawan belum diisi (wajib dilengkapi jika ingin menambah staf baru).');
                    } else if (usernameVal.length < 3) {
                        setError(usernameEl, 'Username karyawan minimal 3 karakter.');
                    } else if (!/^\S+$/.test(usernameVal)) {
                        setError(usernameEl, 'Username tidak boleh mengandung spasi (karakter unik seperti @, #, $, ., _, - diperbolehkan).');
                    }
                }
                if (passwordEl) {
                    if (passwordVal === '') {
                        setError(passwordEl, 'Password awal belum diisi (wajib dilengkapi jika nama atau username karyawan diisi).');
                    } else if (passwordVal.length < 8) {
                        setError(passwordEl, 'Password awal minimal 8 karakter.');
                    }
                }
            }
        }

        // 2. Cek format username pada form pengguna
        const usernameEl = byName('username');
        if (
            form.id !== 'form-setup-semua' &&
            usernameEl &&
            usernameEl.value.trim() !== '' &&
            usernameEl.getAttribute('minlength') &&
            !/^\S+$/.test(usernameEl.value.trim())
        ) {
            setError(usernameEl, 'Username tidak boleh mengandung spasi (karakter unik seperti @, #, $, ., _, - diperbolehkan).');
        }

        // 3. Cek kecocokan password & password_confirmation
        const passEl = byName('password');
        const passConfEl = byName('password_confirmation');
        if (passEl && passConfEl) {
            if (passConfEl.value === '') {
                setError(passConfEl, 'Konfirmasi password belum diisi.');
            } else if (passConfEl.value !== passEl.value) {
                setError(passConfEl, 'Konfirmasi password tidak cocok dengan password baru.');
            }
        }

        // 4. Generator kamar per lantai: sampai >= dari dan maksimal 100 kamar per baris
        const lantaiRows = new Map();
        for (const el of Array.from(form.elements)) {
            const match = (el.name || '').match(/^lantai\[(\d+)\]\[(dari|sampai)\]$/);
            if (!match) {
                continue;
            }
            const idx = match[1];
            const kolom = match[2];
            if (!lantaiRows.has(idx)) {
                lantaiRows.set(idx, {});
            }
            lantaiRows.get(idx)[kolom] = el;
        }
        for (const row of lantaiRows.values()) {
            if (row.dari && row.sampai && row.dari.value !== '' && row.sampai.value !== '') {
                const dari = Number(row.dari.value);
                const sampai = Number(row.sampai.value);
                if (Number.isFinite(dari) && Number.isFinite(sampai)) {
                    if (sampai < dari) {
                        setError(row.sampai, 'Nomor kamar akhir tidak boleh lebih kecil dari nomor kamar awal.');
                    } else if (sampai - dari + 1 > 100) {
                        setError(row.sampai, 'Maksimal 100 kamar per baris rentang lantai.');
                    }
                }
            }
        }

        // 5. Kontrak penghuni: tanggal keluar rencana / aktual tidak boleh sebelum tanggal masuk
        const tglMasukEl = byName('tanggal_masuk');
        const tglRencanaEl = byName('tanggal_keluar_rencana');
        const tglKeluarEl = byName('tanggal_keluar');

        if (tglMasukEl && tglMasukEl.value && tglRencanaEl && tglRencanaEl.value && tglRencanaEl.value < tglMasukEl.value) {
            setError(tglRencanaEl, 'Tanggal keluar rencana tidak boleh sebelum tanggal masuk.');
        }

        if (tglMasukEl && tglMasukEl.value && tglKeluarEl && tglKeluarEl.value && tglKeluarEl.value < tglMasukEl.value) {
            setError(tglKeluarEl, 'Tanggal keluar tidak boleh sebelum tanggal masuk.');
        }

        // 6. Form laundry: wajib berat atau minimal 1 item terpisah, format desimal aman, & konfirmasi > 30 kg
        if (form.id === 'form-laundry') {
            const beratEl = byName('berat_kg');
            const konfirmasiEl = byName('konfirmasi_berat_besar');
            const biayaEl = byName('biaya_utama');
            const itemNamaList = Array.from(form.elements).filter((el) =>
                /^item\[\d+\]\[nama\]$/.test(el.name || '')
            );

            const beratRaw = (beratEl?.value || '').trim();
            if (beratRaw === '' && itemNamaList.length === 0 && beratEl) {
                setError(beratEl, 'Isi berat cucian utama atau tambahkan minimal satu item terpisah.');
            } else if (beratEl && beratRaw !== '') {
                const normal = beratRaw.replace(',', '.');
                if (!/^\d+(?:\.\d{1,2})?$/.test(normal)) {
                    setError(beratEl, 'Format berat tidak valid (contoh: 2,4 atau 3.15).');
                } else {
                    const angka = Number(normal);
                    if (angka <= 0) {
                        setError(beratEl, 'Berat cucian utama harus lebih dari 0 kg.');
                    } else if (angka > 99.99) {
                        setError(beratEl, 'Berat cucian utama maksimal 99,99 kg.');
                    } else if (angka > 30 && konfirmasiEl && !konfirmasiEl.checked) {
                        setError(
                            konfirmasiEl,
                            'Berat melebihi 30 kg. Centang "Saya yakin berat cucian ini benar" untuk melanjutkan.'
                        );
                    }
                }
            }

            if (biayaEl && biayaEl.value.trim() !== '' && !/^\d+$/.test(biayaEl.value.trim())) {
                setError(biayaEl, 'Biaya utama harus berupa angka bulat rupiah.');
            }
        }
    };

    window.__validasiFormSebelumKonfirmasi = (form) => {
        if (!(form instanceof HTMLFormElement)) {
            return true;
        }

        const daftarElemen = Array.from(form.elements).filter(
            (el) => el.name && !el.disabled && !['submit', 'button', 'reset', 'hidden'].includes(el.type)
        );

        for (const el of daftarElemen) {
            bersihkanErrorField(el);
        }

        const errorMap = new Map();
        const catatError = (el, pesan) => {
            if (el && pesan && !errorMap.has(el)) {
                errorMap.set(el, pesan);
            }
        };

        for (const el of daftarElemen) {
            if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
                catatError(el, pesanConstraintHtml(el));
            }
        }

        validasiAturanKhusus(form, catatError);

        if (errorMap.size === 0) {
            return true;
        }

        let firstInvalid = null;
        for (const [el, pesan] of errorMap.entries()) {
            tampilkanErrorField(el, pesan);
            if (!firstInvalid) {
                firstInvalid = el;
            }
        }

        if (firstInvalid && typeof firstInvalid.focus === 'function') {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
        }

        window.dispatchEvent(
            new CustomEvent('toast', {
                detail: {
                    tipe: 'danger',
                    pesan: `Periksa kembali isian formulir: ${errorMap.values().next().value}`,
                },
            })
        );

        return false;
    };

    const bersihkanSaatEdit = (event) => {
        const el = event.target;
        if (!(el instanceof HTMLElement) || !('name' in el)) {
            return;
        }

        bersihkanErrorField(el);

        if (el.form && el.form.id === 'form-setup-semua' && ['name', 'username', 'password'].includes(el.name)) {
            for (const namaStaf of ['name', 'username', 'password']) {
                const target = Array.from(el.form.elements).find((item) => item.name === namaStaf);
                if (target && target.value.trim() === '') {
                    bersihkanErrorField(target);
                }
            }
        }

        if (el.form && ['password', 'password_confirmation'].includes(el.name)) {
            const conf = Array.from(el.form.elements).find((item) => item.name === 'password_confirmation');
            if (conf) {
                bersihkanErrorField(conf);
            }
        }
    };

    document.addEventListener('input', bersihkanSaatEdit);
    document.addEventListener('change', bersihkanSaatEdit);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if ((form.method || '').toLowerCase() !== 'post' || form.dataset.bypassDirty === 'true') {
            return;
        }

        // Form dengan dialog konfirmasi akan divalidasi oleh initConfirmDialog sebelum dialog muncul
        if (form.dataset.confirmTitle) {
            return;
        }

        if (!window.__validasiFormSebelumKonfirmasi(form)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });
}

function initDirtyFormGuard() {
    const dialogDraf = document.getElementById('dialog-draf-keluar');
    const lanjutBtn = document.getElementById('dialog-draf-lanjut');
    const buangBtn = document.getElementById('dialog-draf-buang');
    const simpanBtn = document.getElementById('dialog-draf-simpan');

    const trackedForms = Array.from(document.querySelectorAll('form[data-track-dirty]'));
    if (trackedForms.length === 0) {
        return;
    }

    const baselines = new WeakMap();
    let sedangSubmitResmi = false;
    let pendingAction = null;

    const daftarElemen = (form, sertakanPassword = true) =>
        Array.from(form.elements).filter((el) => {
            if (!el.name || el.disabled) {
                return false;
            }
            if (['_token', '_method', 'aktifkan_sekarang', 'ref'].includes(el.name)) {
                return false;
            }
            if (['submit', 'button', 'reset', 'file'].includes(el.type)) {
                return false;
            }
            if (!sertakanPassword && el.type === 'password') {
                return false;
            }
            return true;
        });

    const ambilNilaiForm = (form, sertakanPassword = true) => {
        const data = {};
        for (const el of daftarElemen(form, sertakanPassword)) {
            if (el.type === 'checkbox') {
                data[el.name] = el.checked;
            } else if (el.type === 'radio') {
                if (el.checked) {
                    data[el.name] = el.value;
                } else if (!(el.name in data)) {
                    data[el.name] = '';
                }
            } else {
                data[el.name] = el.value ?? '';
            }
        }
        return data;
    };

    const terapkanNilaiForm = (form, data) => {
        if (!data || typeof data !== 'object') {
            return;
        }
        form.dispatchEvent(new CustomEvent('draf-pulihkan', { detail: data }));
        for (const el of daftarElemen(form, true)) {
            if (!(el.name in data)) {
                continue;
            }
            const val = data[el.name];
            if (el.type === 'checkbox') {
                el.checked = Boolean(val);
                el.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (el.type === 'radio') {
                el.checked = el.value === String(val);
                if (el.checked) {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } else {
                el.value = String(val ?? '');
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    };

    const hapusDrafTersimpan = (form) => {
        const key = form.dataset.draftKey;
        if (!key) {
            return;
        }
        try {
            localStorage.removeItem(DRAFT_PREFIX + key);
        } catch {
            // Abaikan bila localStorage tidak tersedia
        }
        const banner = form.querySelector('[data-draft-banner]');
        if (banner) {
            banner.remove();
        }
    };

    const simpanKeDraf = (form) => {
        const key = form.dataset.draftKey;
        if (!key) {
            return;
        }
        const payload = {
            waktu: new Date().toISOString(),
            nilai: ambilNilaiForm(form, false),
        };
        try {
            localStorage.setItem(DRAFT_PREFIX + key, JSON.stringify(payload));
        } catch {
            // Abaikan bila storage penuh
        }
        tampilkanBannerDraf(form);
    };

    const tampilkanBannerDraf = (form) => {
        if (form.querySelector('[data-draft-banner]')) {
            return;
        }
        const banner = document.createElement('div');
        banner.setAttribute('data-draft-banner', 'true');
        banner.className =
            'mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gold/40 bg-gold-container px-4 py-3 text-xs text-on-gold sm:text-sm';

        const teks = document.createElement('div');
        teks.className = 'font-medium';
        teks.textContent = 'Draf perubahan yang belum disimpan telah dipulihkan ke formulir ini.';

        const resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className =
            'inline-flex min-h-9 items-center justify-center rounded-lg border border-on-gold/30 bg-surface px-3 text-xs font-bold text-on-gold hover:bg-sunken';
        resetBtn.textContent = 'Hapus Draf & Reset';
        resetBtn.addEventListener('click', () => {
            const baseline = baselines.get(form);
            if (baseline) {
                terapkanNilaiForm(form, baseline);
            }
            hapusDrafTersimpan(form);
        });

        banner.appendChild(teks);
        banner.appendChild(resetBtn);
        form.prepend(banner);
    };

    for (const form of trackedForms) {
        baselines.set(form, ambilNilaiForm(form, true));

        const key = form.dataset.draftKey;
        if (key) {
            try {
                const raw = localStorage.getItem(DRAFT_PREFIX + key);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (parsed && parsed.nilai) {
                        terapkanNilaiForm(form, parsed.nilai);
                        tampilkanBannerDraf(form);
                    }
                }
            } catch {
                // Abaikan JSON draf rusak
            }
        }
    }

    const isFormDirty = (form) => {
        const baseline = baselines.get(form);
        if (!baseline) {
            return false;
        }
        const current = ambilNilaiForm(form, true);
        const keys = new Set([...Object.keys(baseline), ...Object.keys(current)]);
        for (const k of keys) {
            if (baseline[k] !== current[k]) {
                return true;
            }
        }
        return false;
    };

    const daftarFormHalamanDirty = () =>
        trackedForms.filter((form) => {
            const parentDialog = form.closest('dialog');
            if (parentDialog && !parentDialog.open) {
                return false;
            }
            return isFormDirty(form);
        });

    const kunciTombolSubmit = (form) => {
        if (!form) {
            return;
        }
        queueMicrotask(() => {
            form.querySelectorAll('button[data-submit-once], input[data-submit-once]').forEach((btn) => {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
            });
        });
    };

    window.__kunciTombolSubmit = kunciTombolSubmit;

    window.__hapusDrafSaatSubmit = (form) => {
        sedangSubmitResmi = true;
        if (form && form.matches && form.matches('form[data-track-dirty]')) {
            hapusDrafTersimpan(form);
        }
        kunciTombolSubmit(form);
    };

    window.__cobaTutupModalDenganCekDraf = (dialogEl) => {
        if (!dialogDraf || !dialogEl || dialogEl === dialogDraf) {
            return false;
        }
        const modalForms = Array.from(dialogEl.querySelectorAll('form[data-track-dirty]')).filter(isFormDirty);
        if (modalForms.length === 0) {
            return false;
        }
        dialogEl.close();
        pendingAction = {
            type: 'modal',
            modal: dialogEl,
            forms: modalForms,
        };
        dialogDraf.showModal();
        lanjutBtn?.focus();
        return true;
    };

    const jalankanPendingAction = () => {
        if (!pendingAction) {
            return;
        }
        const action = pendingAction;
        pendingAction = null;

        if (action.type === 'link' && action.href) {
            sedangSubmitResmi = true;
            window.location.assign(action.href);
        } else if (action.type === 'form' && action.targetForm) {
            sedangSubmitResmi = true;
            action.targetForm.dataset.bypassDirty = 'true';
            if (typeof action.targetForm.requestSubmit === 'function') {
                action.targetForm.requestSubmit(action.submitter || null);
            } else {
                HTMLFormElement.prototype.submit.call(action.targetForm);
            }
        }
    };

    if (dialogDraf && lanjutBtn && buangBtn && simpanBtn) {
        lanjutBtn.addEventListener('click', () => {
            const action = pendingAction;
            pendingAction = null;
            if (dialogDraf.open) {
                dialogDraf.close();
            }
            if (action?.type === 'modal' && action.modal) {
                action.modal.showModal();
            }
        });

        buangBtn.addEventListener('click', () => {
            if (pendingAction?.forms) {
                for (const form of pendingAction.forms) {
                    const baseline = baselines.get(form);
                    if (baseline) {
                        terapkanNilaiForm(form, baseline);
                    }
                    hapusDrafTersimpan(form);
                }
            }
            if (dialogDraf.open) {
                dialogDraf.close();
            }
            jalankanPendingAction();
        });

        simpanBtn.addEventListener('click', () => {
            if (pendingAction?.forms) {
                for (const form of pendingAction.forms) {
                    simpanKeDraf(form);
                }
            }
            if (dialogDraf.open) {
                dialogDraf.close();
            }
            if (pendingAction?.type !== 'modal') {
                window.dispatchEvent(
                    new CustomEvent('toast', {
                        detail: { tipe: 'ok', pesan: 'Perubahan disimpan sebagai draf lokal.' },
                    })
                );
            }
            jalankanPendingAction();
        });

        dialogDraf.addEventListener('click', (event) => {
            if (event.target === dialogDraf) {
                lanjutBtn.click();
            }
        });
    }

    document.addEventListener(
        'click',
        (event) => {
            if (!dialogDraf || sedangSubmitResmi) {
                return;
            }
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }
            const anchor = event.target.closest('a[href]');
            if (!anchor) {
                return;
            }
            const rawHref = anchor.getAttribute('href') || '';
            if (
                rawHref === '' ||
                rawHref.startsWith('#') ||
                rawHref.startsWith('javascript:') ||
                anchor.target === '_blank' ||
                anchor.hasAttribute('download')
            ) {
                return;
            }

            const dirtyForms = daftarFormHalamanDirty();
            if (dirtyForms.length === 0) {
                return;
            }

            event.preventDefault();
            pendingAction = {
                type: 'link',
                href: anchor.href,
                forms: dirtyForms,
            };
            dialogDraf.showModal();
            lanjutBtn?.focus();
        },
        true
    );

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || sedangSubmitResmi) {
                return;
            }

            if (form.matches('form[data-track-dirty]')) {
                if (!form.dataset.confirmTitle) {
                    const lolosValidasi = !window.__validasiFormSebelumKonfirmasi || window.__validasiFormSebelumKonfirmasi(form);
                    if (!lolosValidasi) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        return;
                    }
                    window.__hapusDrafSaatSubmit(form);
                }
                return;
            }

            if (form.dataset.bypassDirty === 'true' || !dialogDraf) {
                return;
            }

            const dirtyForms = daftarFormHalamanDirty();
            if (dirtyForms.length === 0) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            pendingAction = {
                type: 'form',
                targetForm: form,
                submitter: event.submitter || null,
                forms: dirtyForms,
            };
            dialogDraf.showModal();
            lanjutBtn?.focus();
        },
        true
    );

    window.addEventListener('beforeunload', (event) => {
        if (sedangSubmitResmi) {
            return;
        }
        if (daftarFormHalamanDirty().length > 0) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}

function initConfirmDialog() {
    const dialog = document.getElementById('dialog-konfirmasi');
    const judulEl = document.getElementById('dialog-konfirmasi-judul');
    const pesanEl = document.getElementById('dialog-konfirmasi-pesan');
    const wadahIkonEl = document.getElementById('dialog-konfirmasi-wadah-ikon');
    const ikonEl = document.getElementById('dialog-konfirmasi-ikon');
    const batalBtn = document.getElementById('dialog-konfirmasi-batal');
    const yaBtn = document.getElementById('dialog-konfirmasi-ya');

    if (!dialog || !judulEl || !pesanEl || !batalBtn || !yaBtn) {
        return;
    }

    let pendingForm = null;
    let pendingSubmitter = null;

    const tutupDialog = () => {
        pendingForm = null;
        pendingSubmitter = null;
        if (dialog.open) {
            dialog.close();
        }
    };

    const aturGaya = (variant) => {
        const kelasYa = {
            danger: 'inline-flex min-h-11 items-center justify-center rounded-xl bg-danger px-4 text-sm font-bold text-white hover:bg-danger/90 focus-visible:outline-2 focus-visible:outline-danger',
            gold: 'inline-flex min-h-11 items-center justify-center rounded-xl bg-gold px-4 text-sm font-bold text-white hover:bg-gold-bright focus-visible:outline-2 focus-visible:outline-gold',
            maroon: 'inline-flex min-h-11 items-center justify-center rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah focus-visible:outline-2 focus-visible:outline-maroon',
        };

        const kelasIkon = {
            danger: 'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger',
            gold: 'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gold-container text-on-gold',
            maroon: 'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-maroon/10 text-maroon',
        };

        const ikonNama = {
            danger: 'fas fa-triangle-exclamation text-lg',
            gold: 'fas fa-circle-check text-lg',
            maroon: 'fas fa-circle-question text-lg',
        };

        const v = kelasYa[variant] ? variant : 'maroon';
        yaBtn.className = kelasYa[v];
        if (wadahIkonEl) {
            wadahIkonEl.className = kelasIkon[v];
        }
        if (ikonEl) {
            ikonEl.className = ikonNama[v];
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const title = form.dataset.confirmTitle;
        if (!title || form.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();

        if (window.__validasiFormSebelumKonfirmasi) {
            if (!window.__validasiFormSebelumKonfirmasi(form)) {
                return;
            }
        } else if (!form.reportValidity()) {
            return;
        }

        // Jika form berada di dalam modal <dialog> lain (mis. modal reset password), tutup dulu agar top-layer rapi.
        const parentModal = form.closest('dialog');
        if (parentModal && parentModal !== dialog && parentModal.open) {
            parentModal.close();
        }

        pendingForm = form;
        pendingSubmitter = event.submitter || null;
        judulEl.textContent = title;
        pesanEl.textContent = form.dataset.confirmMessage || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
        yaBtn.textContent = form.dataset.confirmLabel || 'Ya, Lanjutkan';
        aturGaya(form.dataset.confirmVariant || 'maroon');

        dialog.showModal();
        batalBtn.focus();
    });

    batalBtn.addEventListener('click', tutupDialog);

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            tutupDialog();
        }
    });

    dialog.addEventListener('cancel', () => {
        pendingForm = null;
        pendingSubmitter = null;
    });

    yaBtn.addEventListener('click', () => {
        if (!pendingForm) {
            tutupDialog();
            return;
        }
        const formToSubmit = pendingForm;
        const submitter = pendingSubmitter;
        pendingForm = null;
        pendingSubmitter = null;
        formToSubmit.dataset.confirmed = 'true';
        if (window.__hapusDrafSaatSubmit) {
            window.__hapusDrafSaatSubmit(formToSubmit);
        }
        if (dialog.open) {
            dialog.close();
        }
        if (typeof formToSubmit.requestSubmit === 'function') {
            formToSubmit.requestSubmit(submitter);
        } else {
            HTMLFormElement.prototype.submit.call(formToSubmit);
        }
        if (window.__kunciTombolSubmit) {
            window.__kunciTombolSubmit(formToSubmit);
        }
    });
}

function initLaporanActions() {
    document.addEventListener('click', (event) => {
        const printBtn = event.target.closest('[data-print-laporan]');
        if (printBtn) {
            window.print();
        }
    });

    const copyTimers = new WeakMap();

    function laporSalin(button, berhasil) {
        const tip = button.nextElementSibling;
        clearTimeout(copyTimers.get(button));
        button.removeAttribute('data-copied');
        button.removeAttribute('data-failed');
        const pesan = berhasil ? 'Tersalin' : 'Gagal menyalin';
        if (tip) {
            tip.textContent = '';
        }
        void button.offsetWidth;
        button.setAttribute(berhasil ? 'data-copied' : 'data-failed', '');
        if (tip) {
            requestAnimationFrame(() => {
                tip.textContent = pesan;
            });
        }
        copyTimers.set(
            button,
            setTimeout(() => {
                button.removeAttribute('data-copied');
                button.removeAttribute('data-failed');
                if (tip) {
                    tip.textContent = '';
                }
            }, 2200),
        );
    }

    async function salinTeksTarget(button, target) {
        const teks = target.matches('input, textarea') ? target.value : target.innerText.trim();
        if (!teks) {
            return;
        }
        try {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                await navigator.clipboard.writeText(teks);
                laporSalin(button, true);
                return;
            }
            if (target.matches('input, textarea')) {
                target.select();
                const sukses = document.execCommand('copy');
                laporSalin(button, sukses);
                return;
            }
            laporSalin(button, false);
        } catch {
            laporSalin(button, false);
        }
    }

    if ('CommandEvent' in window) {
        document.addEventListener(
            'command',
            (event) => {
                if (event.command === '--copy' && event.source && event.target) {
                    salinTeksTarget(event.source, event.target);
                }
            },
            true,
        );
    } else {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('button[command="--copy"]');
            if (!button) {
                return;
            }
            const target = document.getElementById(button.getAttribute('commandfor') || '');
            if (target) {
                salinTeksTarget(button, target);
            }
        });
    }

    if (typeof navigator.share === 'function') {
        document.querySelectorAll('[data-share-target]').forEach((btn) => {
            btn.classList.remove('hidden');
            btn.classList.add('inline-flex');
            btn.addEventListener('click', async () => {
                const target = document.getElementById(btn.dataset.shareTarget || '');
                if (!target) {
                    return;
                }
                const text = target.matches('input, textarea') ? target.value : target.innerText.trim();
                try {
                    await navigator.share({
                        title: btn.dataset.shareTitle || 'Rekap Laundry',
                        text,
                    });
                } catch {
                    // Pengguna membatalkan dialog bagikan native
                }
            });
        });
    }
}

window.Alpine = Alpine;
Alpine.start();
initInlineValidation();
initDirtyFormGuard();
initConfirmDialog();
initLaporanActions();
