@props([
    'status' => 'normal',
])

@php
    $gaya = match ($status) {
        'normal', 'aktif', 'ok' => 'bg-ok/10 text-ok border-ok/20',
        'mendekati', 'warn', 'draf' => 'bg-gold-container text-on-gold border-gold/25',
        'lewat', 'danger', 'nonaktif' => 'bg-danger/10 text-danger border-danger/20',
        'booking' => 'bg-info/10 text-info border-info/20',
        'keluar', 'selesai', 'arsip' => 'bg-sunken text-muted border-line-strong',
        default => 'bg-info/10 text-info border-info/20',
    };

    $labelDefault = match ($status) {
        'normal' => 'Normal',
        'mendekati' => 'Mendekati kuota',
        'lewat' => 'Lewat kuota',
        'booking' => 'Booking',
        'keluar', 'selesai' => 'Selesai',
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
        'draf' => 'Draf',
        'arsip' => 'Arsip',
        default => ucfirst((string) $status),
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium {$gaya}"]) }}>
    {{ $slot->isNotEmpty() ? $slot : $labelDefault }}
</span>
