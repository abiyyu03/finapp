@props([
    'name',
    'title' => 'Are you sure?',
    'description' => null,
    'confirmLabel' => 'Confirm',
    'confirmingLabel' => null,
    'variant' => 'danger',
    'action' => null,
    'method' => 'DELETE',
])

<x-modal :name="$name">
    <div class="p-6">
        <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>

        @if ($description)
            <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
        @endif

        <div class="mt-6 flex justify-end gap-3">
            <x-button variant="secondary" x-on:click="$dispatch('close')">Cancel</x-button>

            @if ($action)
                {{-- x-data scopes a per-form "submitting" flag so a double
                     click can't fire the request twice while the page hasn't
                     navigated away yet (spec biz §33's duplicate-request
                     rule, backed here by matching UI, not just the
                     idempotent server-side guard). --}}
                <form method="POST" action="{{ $action }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
                    @csrf
                    @if (strtoupper($method) !== 'POST')
                        @method($method)
                    @endif
                    <x-button type="submit" :variant="$variant" x-bind:disabled="submitting">
                        <span x-show="!submitting">{{ $confirmLabel }}</span>
                        <span x-show="submitting" x-cloak>{{ $confirmingLabel ?? $confirmLabel.'…' }}</span>
                    </x-button>
                </form>
            @else
                <x-button :variant="$variant" x-on:click="$dispatch('close')">{{ $confirmLabel }}</x-button>
            @endif
        </div>
    </div>
</x-modal>
