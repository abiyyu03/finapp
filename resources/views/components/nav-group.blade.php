@props(['label', 'active' => false])

<div>
    <p @class([
        'px-3 text-xs font-semibold uppercase tracking-wide',
        'text-emerald-700' => $active,
        'text-slate-400' => ! $active,
    ])>
        {{ $label }}
    </p>
    <div class="mt-1.5 space-y-0.5">
        {{ $slot }}
    </div>
</div>
