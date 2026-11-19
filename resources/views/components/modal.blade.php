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
    x-data
    x-on:buka-modal.window="if ($event.detail === '{{ $id }}') $el.showModal()"
    x-on:tutup-modal.window="if ($event.detail === '{{ $id }}') $el.close()"
    x-on:click="if ($event.target === $el && '{{ $closedby }}' === 'any') $el.close()"
    class="m-auto w-[calc(100%-2rem)] {{ $lebar }} rounded-2xl border border-line bg-surface p-0 text-ink shadow-xl"
>
    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
        <h2 id="{{ $id }}-title" class="text-base font-bold text-ink">{{ $judul }}</h2>
        <button
            type="button"
            x-on:click="$el.closest('dialog').close()"
            aria-label="Tutup dialog"
            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl text-muted hover:bg-sunken hover:text-ink focus-visible:outline-2 focus-visible:outline-maroon"
        >
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="p-5">
        {{ $slot }}
    </div>
</dialog>
