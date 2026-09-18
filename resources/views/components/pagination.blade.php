@props(['paginator'])

@if ($paginator->hasPages())
    <div {{ $attributes->class('border-t border-slate-200 bg-white px-4 py-3') }}>
        {{ $paginator->onEachSide(1)->links() }}
    </div>
@endif
