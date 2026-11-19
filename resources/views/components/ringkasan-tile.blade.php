@props([
    'label' => '',
    'nilai' => '0',
    'keterangan' => null,
    'aksen' => 'ink',
])

@php
    $warnaNilai = match ($aksen) {
        'maroon' => 'text-maroon',
        'gold' => 'text-gold',
        'ok' => 'text-ok',
        'danger' => 'text-danger',
        default => 'text-ink',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-alt p-3.5']) }}>
    <div class="text-xs font-medium text-muted">{{ $label }}</div>
    <div class="mt-1 text-lg font-bold {{ $warnaNilai }}">{{ $nilai }}</div>
    @if ($keterangan)
        <div class="mt-0.5 text-[11px] text-subtle">{{ $keterangan }}</div>
    @endif
</div>
