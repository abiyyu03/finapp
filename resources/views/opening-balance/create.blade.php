@extends('layouts.admin')

@section('title', 'Opening Balance')

@section('breadcrumb')
    <span class="text-slate-400">Accounting</span> / <span class="text-slate-700">Opening Balance</span>
@endsection

@section('content')
    <div class="mx-auto max-w-3xl py-6">
        <h1 class="text-lg font-semibold text-slate-900">Opening Balance</h1>
        <p class="mt-1 text-sm text-slate-500">
            {{ auth()->user()->activeCompany()->name }}'s starting financial position. This posts immediately through
            the same engine as any other journal — there's no separate mechanism for it (spec §22).
        </p>

        <form method="POST" action="{{ route('opening-balance.store') }}" class="mt-4 space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            @php $journal = null; $autoPosts = true; @endphp
            @include('journals._form')

            <div class="flex justify-end gap-3">
                <x-button as="a" href="{{ route('journals.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Post Opening Balance</x-button>
            </div>
        </form>
    </div>
@endsection
