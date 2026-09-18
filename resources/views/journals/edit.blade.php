@extends('layouts.admin')

@section('title', 'Edit Journal Entry')

@section('breadcrumb')
    <span class="text-slate-400">Accounting</span> /
    <a href="{{ route('journals.index') }}" class="text-slate-400 hover:text-slate-600">Journal Entries</a> /
    <span class="text-slate-700">{{ $journal->journal_number }}</span>
@endsection

@section('content')
    <div class="mx-auto max-w-3xl py-6">
        <h1 class="text-lg font-semibold text-slate-900">Edit {{ $journal->journal_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">Still a draft — editable until it's posted.</p>

        <form method="POST" action="{{ route('journals.update', $journal) }}" class="mt-4 space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            @include('journals._form')

            <div class="flex justify-end gap-3">
                <x-button as="a" href="{{ route('journals.show', $journal) }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Changes</x-button>
            </div>
        </form>
    </div>
@endsection
