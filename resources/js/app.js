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

window.Alpine = Alpine;
Alpine.start();
