@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
])

@php
    // Guarded default: ShareErrorsFromSession shares $errors app-wide, but a
    // component rendered outside that middleware chain (e.g. in isolation)
    // shouldn't hard-crash over it.
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $hasError = $errors->has($name);
@endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-slate-700">
            {{ $label }}
        </label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        {{ $attributes->class([
            'block w-full rounded-md border px-3 py-2 text-sm shadow-sm placeholder:text-slate-400',
            'focus:outline-none focus:ring-2 focus:ring-offset-0',
            'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500' => $hasError,
            'border-slate-300 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500' => ! $hasError,
        ]) }}
    />

    @if ($hasError)
        <p class="mt-1 text-xs text-red-600">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
