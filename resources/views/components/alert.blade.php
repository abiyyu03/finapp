@props(['variant' => 'info'])

@php
    $variants = [
        'success' => ['bg-emerald-50 border-emerald-200 text-emerald-800', 'text-emerald-500'],
        'error' => ['bg-red-50 border-red-200 text-red-800', 'text-red-500'],
        'warning' => ['bg-amber-50 border-amber-200 text-amber-800', 'text-amber-500'],
        'info' => ['bg-slate-50 border-slate-200 text-slate-700', 'text-slate-400'],
    ];

    [$colors, $iconColor] = $variants[$variant] ?? $variants['info'];

    $icons = [
        'success' => 'M9 12.75l2.25 2.25 4.5-4.5m6 1.5a9 9 0 11-18 0 9 9 0 0118 0z',
        'error' => 'M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
        'info' => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
    ];
@endphp

<div {{ $attributes->class(['flex gap-2.5 rounded-md border px-4 py-3 text-sm', $colors]) }} role="alert">
    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $iconColor }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$variant] ?? $icons['info'] }}" />
    </svg>
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
