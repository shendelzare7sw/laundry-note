@props([
    'tipe' => 'biaya',
    'label' => '',
    'nilai' => '',
    'catatan' => null,
])

@php
    $gaya = match ($tipe) {
        'biaya' => 'bg-ok/10 text-ok border-ok/20',
        'lewat' => 'bg-danger/10 text-danger border-danger/20',
        'terpisah' => 'bg-gold-container text-on-gold border-gold/25',
        'wa' => 'bg-ok text-white border-ok',
        default => 'bg-sunken text-ink border-line-strong',
    };
@endphp

<div {{ $attributes->merge(['class' => "flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-sm font-medium {$gaya}"]) }}>
    <div class="min-w-0">
        <span>{{ $label }}</span>
        @if ($catatan)
            <span class="ml-1 text-xs font-normal opacity-85">({{ $catatan }})</span>
        @endif
    </div>
    <div class="shrink-0 font-bold">
        {{ $slot->isNotEmpty() ? $slot : $nilai }}
    </div>
</div>
