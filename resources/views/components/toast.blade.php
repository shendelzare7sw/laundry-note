@php
    $toastSession = session('toast');
@endphp

<div
    role="region"
    aria-label="Notifikasi"
    aria-live="polite"
    x-data="toastBar(@js($toastSession['tipe'] ?? 'ok'), @js($toastSession['pesan'] ?? ''))"
    x-on:toast.window="mulai($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex justify-center px-4 lg:bottom-6"
>
    <div
        x-show="tampil"
        x-cloak
        x-transition
        :class="{
            'bg-ink text-white border-ink': tipe === 'ok',
            'bg-danger text-white border-danger': tipe === 'danger',
            'bg-gold-container text-on-gold border-gold': tipe === 'warn',
        }"
        class="pointer-events-auto flex w-full max-w-md items-center justify-between gap-3 rounded-xl border px-4 py-2.5 text-sm shadow-lg"
    >
        <span x-text="pesan" class="font-medium"></span>
        <button
            type="button"
            x-on:click="tutup"
            aria-label="Tutup notifikasi"
            class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg opacity-85 hover:opacity-100"
        >
            <i class="fas fa-xmark text-sm" aria-hidden="true"></i>
        </button>
    </div>
</div>
