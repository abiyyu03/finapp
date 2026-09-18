@extends('layouts.admin')

@section('title', 'New Bank Account')

@section('breadcrumb')
    <span class="text-slate-400">Cash &amp; Bank</span> /
    <a href="{{ route('bank-accounts.index') }}" class="text-slate-400 hover:text-slate-600">Bank Accounts</a> /
    <span class="text-slate-700">New</span>
@endsection

@section('content')
    <div class="mx-auto max-w-2xl py-6">
        <h1 class="text-lg font-semibold text-slate-900">New Bank Account</h1>

        <form method="POST" action="{{ route('bank-accounts.store') }}" class="mt-4 space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            @php $bankAccount = null; @endphp
            @include('bank-accounts._form')

            <div class="flex justify-end gap-3">
                <x-button as="a" href="{{ route('bank-accounts.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Create Bank Account</x-button>
            </div>
        </form>
    </div>
@endsection
