@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
])

@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $hasError = $errors->has($name);
    $current = old($name, $selected);
@endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-slate-700">
            {{ $label }}
        </label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm',
            'focus:outline-none focus:ring-2 focus:ring-offset-0',
            'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500' => $hasError,
            'border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500' => ! $hasError,
        ]) }}
    >
        @if ($placeholder)
            <option value="" @selected(is_null($current))>{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if ($hasError)
        <p class="mt-1 text-xs text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
