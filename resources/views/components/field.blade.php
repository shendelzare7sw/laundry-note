@props([
    'label',
    'name',
    'id' => null,
    'type' => 'text',
    'value' => '',
    'required' => false,
    'placeholder' => '',
    'autocomplete' => null,
    'hint' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorId = $fieldId.'-error';
    $hasError = $errors->has($name);
    $currentValue = old($name, $value);
    $isPassword = $type === 'password';
@endphp

<div class="space-y-1.5">
    <label for="{{ $fieldId }}" class="block text-sm font-medium text-ink">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @elseif ($isPassword)
        <div class="relative" x-data="passwordField">
            <input
                id="{{ $fieldId }}"
                name="{{ $name }}"
                x-bind:type="show ? 'text' : 'password'"
                @if ($required) required @endif
                @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif
                @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                aria-invalid="{{ $hasError ? 'true' : 'false' }}"
                @if ($hasError) aria-describedby="{{ $errorId }}" @endif
                {{ $attributes->merge([
                    'class' => 'block min-h-11 w-full rounded-xl border bg-surface py-2.5 pl-3.5 pr-12 text-sm text-ink placeholder:text-subtle focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20 '.($hasError ? 'border-danger' : 'border-line-strong'),
                ]) }}
            >
            <button
                type="button"
                x-on:click="toggle"
                x-bind:aria-label="show ? 'Sembunyikan password' : 'Lihat password'"
                class="absolute right-1 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-lg text-muted hover:bg-sunken hover:text-ink focus-visible:outline-2 focus-visible:outline-maroon"
            >
                <i class="fas" x-bind:class="show ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
            </button>
        </div>
    @else
        <input
            id="{{ $fieldId }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $currentValue }}"
            @if ($required) required @endif
            @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            @if ($hasError) aria-describedby="{{ $errorId }}" @endif
            {{ $attributes->merge([
                'class' => 'block min-h-11 w-full rounded-xl border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-subtle focus:border-maroon focus:outline-none focus:ring-2 focus:ring-maroon/20 '.($hasError ? 'border-danger' : 'border-line-strong'),
            ]) }}
        >
    @endif

    @if ($hint && ! $hasError)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="text-xs font-medium text-danger">{{ $message }}</p>
    @enderror
</div>
