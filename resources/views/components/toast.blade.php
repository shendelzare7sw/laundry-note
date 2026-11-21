@php
    $toastSession = session('toast');
    if ($toastSession === null && isset($errors) && $errors->any()) {
        $toastSession = [
            'tipe' => 'danger',
            'pesan' => (string) $errors->first(),
        ];
    }
@endphp

<div
    role="region"
    aria-label="Notifikasi"
    aria-live="polite"
    x-data="toastBar(@js($toastSession['tipe'] ?? 'ok'), @js($toastSession['pesan'] ?? ''), @js($toastSession['aksi_url'] ?? ''), @js($toastSession['aksi_label'] ?? ''))"
    x-on:toast.window="mulai($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex justify-center px-4 lg:bottom-6"
>
    <div
        x-show="tampil"
        x-cloak
        x-transition
        x-on:mouseenter="jeda"
        x-on:mouseleave="lanjutkan"
        x-on:focusin="jeda"
        x-on:focusout="lanjutkan"
        :class="{
            'bg-ink text-white border-ink': tipe === 'ok',
            'bg-danger text-white border-danger': tipe === 'danger',
            'bg-gold-container text-on-gold border-gold': tipe === 'warn',
        }"
        class="pointer-events-auto flex w-full max-w-md items-center justify-between gap-3 rounded-xl border px-4 py-2.5 text-sm shadow-lg"
    >
        <span x-text="pesan" class="font-medium"></span>

        <div class="flex shrink-0 items-center gap-1.5">
            <template x-if="aksiUrl">
                <form method="POST" :action="aksiUrl" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        x-text="aksiLabel || 'Batalkan'"
                        class="inline-flex min-h-9 items-center justify-center rounded-lg bg-white/20 px-3 text-xs font-bold text-current hover:bg-white/30 focus-visible:outline-2 focus-visible:outline-current"
                    ></button>
                </form>
            </template>

            <button
                type="button"
                x-on:click="tutup"
                aria-label="Tutup notifikasi"
                class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg opacity-85 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-current"
            >
                <i class="fas fa-xmark text-sm" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>
