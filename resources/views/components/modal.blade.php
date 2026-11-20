@props([
    'id',
    'judul',
    'lebar' => 'max-w-lg',
    'closedby' => 'any',
])

<dialog
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-title"
    closedby="{{ $closedby }}"
    x-data="modalDialog('{{ $id }}', '{{ $closedby }}')"
    x-on:buka-modal.window="buka($event.detail)"
    x-on:tutup-modal.window="tutup($event.detail)"
    x-on:click="klikLuar($event)"
    class="m-auto w-[calc(100%-2rem)] {{ $lebar }} rounded-2xl border border-line bg-surface p-0 text-ink shadow-xl"
>
    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
        <h2 id="{{ $id }}-title" class="text-base font-bold text-ink">{{ $judul }}</h2>
        <button
            type="button"
            data-modal-close
            aria-label="Tutup dialog"
            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl text-muted hover:bg-sunken hover:text-ink focus-visible:outline-2 focus-visible:outline-maroon"
        >
            <i class="fas fa-xmark text-base" aria-hidden="true"></i>
        </button>
    </div>

    <div class="p-5">
        {{ $slot }}
    </div>
</dialog>
