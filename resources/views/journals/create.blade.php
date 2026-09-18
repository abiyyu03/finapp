@extends('layouts.admin')

@section('title', 'New Journal Entry')

@section('breadcrumb')
    <span class="text-slate-400">Accounting</span> /
    <a href="{{ route('journals.index') }}" class="text-slate-400 hover:text-slate-600">Journal Entries</a> /
    <span class="text-slate-700">New</span>
@endsection

@section('content')
    <div class="mx-auto max-w-3xl py-6">
        <h1 class="text-lg font-semibold text-slate-900">New Journal Entry</h1>
        <p class="mt-1 text-sm text-slate-500">Saved as a draft — it won't appear in any report until it's posted.</p>

        <form method="POST" action="{{ route('journals.store') }}" class="mt-4 space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            @php $journal = null; @endphp
            @include('journals._form')

            <div class="flex justify-end gap-3">
                <x-button as="a" href="{{ route('journals.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Draft</x-button>
            </div>
        </form>
    </div>
@endsection
