<dialog
    id="dialog-konfirmasi"
    aria-labelledby="dialog-konfirmasi-judul"
    aria-describedby="dialog-konfirmasi-pesan"
    closedby="any"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-line bg-surface p-0 text-ink shadow-xl"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start gap-3.5">
            <span
                id="dialog-konfirmasi-wadah-ikon"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-maroon/10 text-maroon"
            >
                <i id="dialog-konfirmasi-ikon" class="fas fa-circle-question text-lg" aria-hidden="true"></i>
            </span>

            <div class="min-w-0 flex-1">
                <h2 id="dialog-konfirmasi-judul" class="text-base font-bold text-ink">
                    Konfirmasi Aksi
                </h2>
                <p id="dialog-konfirmasi-pesan" class="mt-1 text-sm leading-relaxed text-muted">
                    Apakah Anda yakin ingin melanjutkan tindakan ini?
                </p>
            </div>
        </div>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button
                id="dialog-konfirmasi-batal"
                type="button"
                autofocus
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line-strong bg-surface px-4 text-sm font-bold text-ink hover:bg-sunken focus-visible:outline-2 focus-visible:outline-maroon"
            >
                Batal
            </button>
            <button
                id="dialog-konfirmasi-ya"
                type="button"
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-maroon px-4 text-sm font-bold text-white hover:bg-maroon-cerah focus-visible:outline-2 focus-visible:outline-maroon"
            >
                Ya, Lanjutkan
            </button>
        </div>
    </div>
</dialog>
