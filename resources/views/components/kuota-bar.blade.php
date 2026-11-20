@props([
    'kuota',
    'ringkas' => false,
])

@php
    $status = $kuota['status'] ?? 'normal';
    $modeKuota = $kuota['mode_kuota'] ?? 'penghuni';
    $kelasWarnaTeks = match ($status) {
        'lewat' => 'text-danger',
        'mendekati' => 'text-warn',
        default => 'text-ok',
    };
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($modeKuota === 'tanpa_kuota')
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="font-bold text-ink">{{ $kuota['terpakai_kg'] }} kg</span>
            <span class="text-muted">Tanpa batas kuota</span>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="font-bold {{ $status === 'lewat' ? 'text-danger' : 'text-ink' }}">
                {{ $kuota['terpakai_kg'] }} / {{ $kuota['kuota_kg'] }} kg
                <span class="font-normal text-muted">({{ $kuota['persen'] }}%)</span>
            </span>

            @if ($ringkas)
                <span class="font-medium {{ $kelasWarnaTeks }}">
                    @if ($kuota['kelebihan_centi'] > 0)
                        +{{ $kuota['kelebihan_kg'] }} kg
                    @else
                        Sisa {{ $kuota['sisa_kg'] }} kg
                    @endif
                </span>
            @else
                <x-lencana :status="$status" />
            @endif
        </div>

        <progress
            class="kuota-progress block h-2.5 w-full rounded-full kuota-{{ $status }}"
            value="{{ $kuota['persen_bar'] }}"
            max="100"
            aria-label="Pemakaian kuota {{ $kuota['terpakai_kg'] }} dari {{ $kuota['kuota_kg'] }} kg"
        >{{ $kuota['persen_bar'] }}%</progress>

        @if (! $ringkas && $status === 'lewat')
            <p class="text-xs font-medium text-danger">
                @if ($kuota['di_bawah_toleransi'])
                    Lewat {{ $kuota['kelebihan_kg'] }} kg (di bawah toleransi {{ $kuota['toleransi_kg'] }} kg — tidak ditagih otomatis)
                @else
                    Lewat {{ $kuota['kelebihan_kg'] }} kg · Estimasi tagihan lewat kuota Rp{{ number_format($kuota['tagihan_otomatis'], 0, ',', '.') }}
                @endif
            </p>
        @elseif (! $ringkas)
            <p class="text-xs text-muted">
                Sisa kuota gratis: {{ $kuota['sisa_kg'] }} kg · {{ $kuota['jumlah_catatan'] }} catatan
            </p>
        @endif
    @endif
</div>
