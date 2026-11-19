@props([
    'judul' => null,
    'subjudul' => null,
    'padding' => 'p-4 sm:p-5',
])

<section {{ $attributes->merge(['class' => "rounded-2xl bg-surface border border-line shadow-xs {$padding}"]) }}>
    @if ($judul || isset($aksi))
        <header class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                @if ($judul)
                    <h2 class="text-base font-bold text-ink">{{ $judul }}</h2>
                @endif
                @if ($subjudul)
                    <p class="mt-0.5 text-xs text-muted">{{ $subjudul }}</p>
                @endif
            </div>
            @isset($aksi)
                <div class="shrink-0">{{ $aksi }}</div>
            @endisset
        </header>
    @endif

    {{ $slot }}
</section>
