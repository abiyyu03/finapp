@props(['name', 'maxWidth' => 'md'])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
    ][$maxWidth] ?? 'sm:max-w-md';
@endphp

<div
    x-data="{ show: false }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' && (show = true)"
    x-on:close.window="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-modal="true"
    role="dialog"
>
    <div
        x-show="show"
        x-transition.opacity
        class="fixed inset-0 bg-slate-900/50"
        @click="show = false"
    ></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-show="show"
            x-transition
            @click.stop
            {{ $attributes->class(['w-full rounded-lg bg-white shadow-xl', $maxWidthClass]) }}
        >
            {{ $slot }}
        </div>
    </div>
</div>
