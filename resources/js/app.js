import '@fontsource/roboto/400.css';
import '@fontsource/roboto/500.css';
import '@fontsource/roboto/700.css';
import Alpine from 'alpinejs';

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
            dialog.close();
        }
    }
});

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

    const tutupDialog = () => {
        pendingForm = null;
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

        if (!form.reportValidity()) {
            return;
        }

        // Jika form berada di dalam modal <dialog> lain (mis. modal reset password), tutup dulu agar top-layer rapi.
        const parentModal = form.closest('dialog');
        if (parentModal && parentModal !== dialog && parentModal.open) {
            parentModal.close();
        }

        pendingForm = form;
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
    });

    yaBtn.addEventListener('click', () => {
        if (!pendingForm) {
            tutupDialog();
            return;
        }
        const formToSubmit = pendingForm;
        pendingForm = null;
        formToSubmit.dataset.confirmed = 'true';
        if (dialog.open) {
            dialog.close();
        }
        HTMLFormElement.prototype.submit.call(formToSubmit);
    });
}

window.Alpine = Alpine;
Alpine.start();
initConfirmDialog();
