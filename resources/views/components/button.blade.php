@props([
    'variant' => 'primary',
    'size' => 'md',
    'as' => 'button',
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:outline-emerald-600',
        'secondary' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus-visible:outline-slate-400',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600',
        'ghost' => 'text-slate-600 hover:bg-slate-100 focus-visible:outline-slate-400',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-4 py-2.5 text-sm',
    ];

    $classes = 'inline-flex items-center justify-center gap-1.5 rounded-md font-medium shadow-sm '
        . 'transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 '
        . 'disabled:cursor-not-allowed disabled:opacity-50 '
        . ($variants[$variant] ?? $variants['primary']) . ' '
        . ($sizes[$size] ?? $sizes['md']);
@endphp

@if ($as === 'a')
    <a {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
