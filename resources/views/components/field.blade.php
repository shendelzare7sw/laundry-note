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
