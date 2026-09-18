@extends('layouts.admin')

@section('title', 'New Cash Account')

@section('breadcrumb')
    <span class="text-slate-400">Cash &amp; Bank</span> /
    <a href="{{ route('cash-accounts.index') }}" class="text-slate-400 hover:text-slate-600">Cash Accounts</a> /
    <span class="text-slate-700">New</span>
@endsection

@section('content')
    <div class="mx-auto max-w-2xl py-6">
        <h1 class="text-lg font-semibold text-slate-900">New Cash Account</h1>

        <form method="POST" action="{{ route('cash-accounts.store') }}" class="mt-4 space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            @php $cashAccount = null; @endphp
            @include('cash-accounts._form')

            <div class="flex justify-end gap-3">
                <x-button as="a" href="{{ route('cash-accounts.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Create Cash Account</x-button>
            </div>
        </form>
    </div>
@endsection
