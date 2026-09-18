@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'block rounded-md px-3 py-1.5 text-sm transition-colors',
        'bg-emerald-50 font-medium text-emerald-700' => $active,
        'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! $active,
    ]) }}
>
    {{ $slot }}
</a>
