@extends('layouts.admin')

@section('title', 'Edit Bank Account')

@section('breadcrumb')
    <span class="text-slate-400">Cash &amp; Bank</span> /
    <a href="{{ route('bank-accounts.index') }}" class="text-slate-400 hover:text-slate-600">Bank Accounts</a> /
    <span class="text-slate-700">{{ $bankAccount->bank_name }}</span>
@endsection

@section('content')
    <div class="mx-auto max-w-2xl py-6">
        <h1 class="text-lg font-semibold text-slate-900">Edit {{ $bankAccount->bank_name }} — {{ $bankAccount->account_name }}</h1>

        <form method="POST" action="{{ route('bank-accounts.update', $bankAccount) }}" class="mt-4 space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            @include('bank-accounts._form')

            <div class="flex justify-end gap-3">
                <x-button as="a" href="{{ route('bank-accounts.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Changes</x-button>
            </div>
        </form>
    </div>
@endsection
