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

    Alpine.data('toastBar', (initialTipe = 'ok', initialPesan = '') => ({
        tampil: Boolean(initialPesan),
        tipe: initialTipe,
        pesan: initialPesan,
        timer: null,
        init() {
            if (this.tampil) {
                this.jadwalkanTutup();
            }
        },
        mulai(detail) {
            this.tipe = detail?.tipe ?? 'ok';
            this.pesan = detail?.pesan ?? '';
            this.tampil = Boolean(this.pesan);
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
            this.timer = setTimeout(() => {
                this.tampil = false;
            }, 5000);
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
            return `Format ${label} tidak sesuai.`;
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
            if (['_token', '_method', 'aktifkan_sekarang'].includes(el.name)) {
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
        for (const el of daftarElemen(form, true)) {
            if (!(el.name in data)) {
                continue;
            }
            const val = data[el.name];
            if (el.type === 'checkbox') {
                el.checked = Boolean(val);
            } else if (el.type === 'radio') {
                el.checked = el.value === String(val);
            } else {
                el.value = String(val ?? '');
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

    window.__hapusDrafSaatSubmit = (form) => {
        sedangSubmitResmi = true;
        if (form && form.matches && form.matches('form[data-track-dirty]')) {
            hapusDrafTersimpan(form);
        }
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
    });
}

window.Alpine = Alpine;
Alpine.start();
initInlineValidation();
initDirtyFormGuard();
initConfirmDialog();
